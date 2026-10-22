<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Async;

use Alama\Arazzo\Contracts\Dependency\DependencyAnalyzer;
use Alama\Arazzo\Contracts\Dependency\DependencyGraph;
use Alama\Arazzo\Contracts\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Contracts\Spec\Action\SubWorkflowFailureAction;
use Alama\Arazzo\Contracts\Spec\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus;
use Alama\Arazzo\Contracts\Spec\Enum\StepStatus;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Contracts\Support\Events\Dispatcher\NullEventDispatcher;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Events\CorrelationPendingEvent;
use Alama\Arazzo\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Events\RunCompletedEvent;
use Alama\Arazzo\Events\RunFailedEvent;
use Alama\Arazzo\Events\StepRetriedEvent;
use Alama\Arazzo\Expression\Data\EvaluationContext;
use Alama\Arazzo\Runner\Execution\Data\RunControlFlow;
use Alama\Arazzo\Runner\Execution\Data\RunPersistence;
use Alama\Arazzo\Runner\Execution\Data\Transition;
use Alama\Arazzo\Runner\Execution\Enum\TransitionType;
use Alama\Arazzo\Runner\Sync\WorkflowEngine;
use Alama\Arazzo\Runtime\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\StateStoreInterface;
use DateTimeImmutable;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * Queue/sync adapter around the canonical {@see WorkflowEngine}.
 *
 * All control-flow decisions come from the engine as Transition objects;
 * this class performs only the side effects those transitions describe:
 * persisting state, dispatching follow-up jobs, completing the registry,
 * appending ledger entries, and emitting framework events.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
class StepOutcomeHandler
{
    private EventDispatcherInterface $events;

    private QueueDriverInterface $queueDriver;

    private WorkflowEngine $workflowEngine;

    private ExecutionRegistryInterface $executionRegistry;

    private EventLedgerInterface $eventLedger;

    private StateStoreInterface $stateStore;

    public function __construct(
        RunPersistence $persistence,
        RunControlFlow $controlFlow,
        private PendingCorrelationRegistryInterface $pendingCorrelations,
        private SubWorkflowInvoker $invoker,
        private EvaluationEngineInterface $evaluationEngine,
        private int $stateTtlSeconds = 86400,
    ) {
        $this->queueDriver = $controlFlow->queueDriver;
        $this->workflowEngine = $controlFlow->workflowEngine;
        $this->events = $controlFlow->events ?? new NullEventDispatcher();
        $this->executionRegistry = $persistence->executionRegistry;
        $this->eventLedger = $persistence->eventLedger;
        $this->stateStore = $persistence->stateStore;
    }

    public function handle(
        ArazzoDocument $document,
        Workflow $workflow,
        Step $step,
        WorkflowContext $context,
        string $executionId,
        bool $criteriaMet,
        bool $suspended = false,
    ): void {
        foreach ($step->io->outputs as $name => $value) {
            $resolved = match (true) {
                $value instanceof Selector => $this->evaluationEngine->evaluateSelector($value, $context, $step->stepId),
                $value instanceof Expression => $this->evaluationEngine->evaluate($value, new EvaluationContext($context, $step->stepId)),
                default => $value,
            };
            $context = $context->withStepOutput($step->stepId, $name, $resolved);
        }

        $state = $this->stateFrom($context);
        $transition = $this->workflowEngine->transition($document, $workflow, $step, $state, $criteriaMet, $suspended);

        /** @var array<string, mixed> $error */
        foreach ($transition->state->errors as $error) {
            if (($error['type'] ?? null) === 'retry_exhausted') {
                $this->eventLedger->append($executionId, 'step.retry_exhausted', [
                    'stepId' => $error['stepId'],
                    'attempts' => $error['attempts'],
                ]);
            }
        }

        match ($transition->type) {
            TransitionType::Next => $this->applyNext($transition, $workflow, $step, $context, $executionId),
            TransitionType::Retry => $this->applyRetry($transition, $document, $workflow, $step, $context, $executionId),
            TransitionType::Goto => $this->applyGoto($transition, $document, $step, $context, $executionId, $criteriaMet),
            TransitionType::Invoke => $this->applyInvoke($document, $workflow, $step, $context, $executionId),
            TransitionType::End => $this->applyEnd($transition, $workflow, $step, $context, $executionId),
            TransitionType::Suspend => $this->applySuspend($step, $context, $executionId),
        };
    }

    /**
     * Route a suspended outcome for an actor-in-the-loop step to the handoff
     * path instead of scheduling a retry: re-register the pending correlation
     * and re-emit the {@see CorrelationPendingEvent} so the actor is asked
     * again. Steps without a handoff signature (transport or engine interaction)
     * keep executor-layer suspension semantics and no-op here.
     */
    private function applySuspend(
        Step $step,
        WorkflowContext $context,
        string $executionId,
    ): void {
        if (!$this->isHandoffCandidate($step)) {
            return;
        }

        $correlationIdValue = $this->resolveCorrelationId($step, $context);
        $channelPath = $step->target->channelPath ?? '';

        $this->pendingCorrelations->create(
            $correlationIdValue,
            $executionId,
            $step->stepId,
            $channelPath,
            $this->stateTtlSeconds,
        );

        $this->events->dispatch(new CorrelationPendingEvent(
            $executionId,
            $context->getWorkflowId() ?? '',
            $step->stepId,
            $correlationIdValue,
            $channelPath,
            new DateTimeImmutable(),
        ));
    }

    private function isHandoffCandidate(Step $step): bool
    {
        return $step->target->interaction !== null
            || ($step->target->action === 'receive' && $step->target->channelPath !== null);
    }

    /**
     * Mirror UnifiedStepCarrier's empty-id record for interaction handoffs
     * without a correlation expression, and never cast structure to string
     * (DF6): an evaluated non-scalar yields an empty correlation id.
     */
    private function resolveCorrelationId(Step $step, WorkflowContext $context): string
    {
        if ($step->target->correlationId === null) {
            return '';
        }

        $evaluated = $this->evaluationEngine->evaluate($step->target->correlationId, new EvaluationContext($context, $step->stepId));

        return is_scalar($evaluated) ? (string) $evaluated : '';
    }

    private function applyNext(
        Transition $transition,
        Workflow $workflow,
        Step $step,
        WorkflowContext $context,
        string $executionId,
    ): void {
        $newContext = $context->withStepStatus($step->stepId, StepStatus::Succeeded);
        $this->stateStore->save($executionId, $this->serialize($newContext), $this->stateTtlSeconds);

        if ($transition->stepId === null) {
            return;
        }

        $targetStep = $this->findStep($workflow, $transition->stepId);
        if ($targetStep === null) {
            return;
        }

        $this->queueDriver->dispatch(new ExecuteStepJob($targetStep, $newContext));
    }

    private function applyRetry(
        Transition $transition,
        ArazzoDocument $document,
        Workflow $workflow,
        Step $step,
        WorkflowContext $context,
        string $executionId,
    ): void {
        $newContext = $context
            ->withStepAttemptIncremented($step->stepId)
            ->withStepStatus($step->stepId, StepStatus::Retrying);

        if ($transition->workflowId !== null && $transition->workflowId !== $context->getWorkflowId()) {
            $newContext = $newContext->withWorkflowId($transition->workflowId);
        }
        if ($transition->stepId !== null && $transition->stepId !== $step->stepId) {
            $newContext = $newContext->withStepStatus($transition->stepId, StepStatus::Pending);
        }

        $this->stateStore->save($executionId, $this->serialize($newContext), $this->stateTtlSeconds);

        $attempt = $context->getStepAttempts($step->stepId);
        $this->events->dispatch(new StepRetriedEvent(
            $executionId,
            $workflow->workflowId,
            $step->stepId,
            $attempt,
            null,
            new DateTimeImmutable(),
        ));

        $targetWorkflow = $this->findWorkflow($document, $transition->workflowId ?? $workflow->workflowId) ?? $workflow;
        $targetStep = $transition->stepId !== null ? $this->findStep($targetWorkflow, $transition->stepId) : null;
        if ($targetStep !== null) {
            $this->queueDriver->dispatch(new ExecuteStepJob($targetStep, $newContext), $transition->delaySeconds);
        }
    }

    private function applyGoto(
        Transition $transition,
        ArazzoDocument $document,
        Step $step,
        WorkflowContext $context,
        string $executionId,
        bool $criteriaMet,
    ): void {
        $status = $criteriaMet ? StepStatus::Succeeded : StepStatus::Failed;

        // Bind any parameters carried on the transition state's inputs.
        $newContext = $context
            ->withWorkflowId($transition->state->workflowId)
            ->withStepStatus($step->stepId, $status)
            ->withInputs($transition->state->inputs);

        $targetWorkflow = $this->findWorkflow($document, $transition->workflowId ?? (string) $context->getWorkflowId());
        if ($targetWorkflow === null) {
            return;
        }

        if ($transition->stepId === null) {
            // Transfer to the start of the target workflow: dispatch its runnable steps.
            $analyzer = new DependencyAnalyzer(new DependencyGraph($targetWorkflow->steps));
            $runnable = $analyzer->getRunnableSteps($newContext);

            $this->stateStore->save($executionId, $this->serialize($newContext), $this->stateTtlSeconds);
            foreach ($runnable as $runnableStep) {
                $this->queueDriver->dispatch(new ExecuteStepJob($runnableStep, $newContext));
            }

            return;
        }

        $targetStep = $this->findStep($targetWorkflow, $transition->stepId);
        if ($targetStep === null) {
            return;
        }

        $newContext = $newContext->withStepStatus($targetStep->stepId, StepStatus::Pending);
        $this->stateStore->save($executionId, $this->serialize($newContext), $this->stateTtlSeconds);
        $this->queueDriver->dispatch(new ExecuteStepJob($targetStep, $newContext));
    }

    private function applyInvoke(
        ArazzoDocument $document,
        Workflow $workflow,
        Step $step,
        WorkflowContext $context,
        string $executionId,
    ): void {
        // Reconstruct the original invoke action to run through the invoker.
        $invoke = $this->findAction($document, $workflow, $step, SubWorkflowSuccessAction::class)
            ?? $this->findAction($document, $workflow, $step, SubWorkflowFailureAction::class);

        if (!$invoke instanceof SubWorkflowSuccessAction && !$invoke instanceof SubWorkflowFailureAction) {
            return;
        }

        $result = $this->invoker->invoke($invoke, $context);

        // The child drew from the shared budget; the parent advances to at
        // least the child's final consumption.
        $stack = $context->getWorkflowCallStack();
        foreach ($result->workflowCallStack as $wfId) {
            if (!in_array($wfId, $stack, true)) {
                $stack[] = $wfId;
            }
        }
        $context = $context->withBudget(max($context->getStepsSpent(), $result->stepsSpent), $stack);

        $context = $context->withWorkflowData($invoke->workflowId, ['inputs' => $result->inputs, 'outputs' => $result->outputs]);
        $context = $context->withStepOutput($step->stepId, $invoke->name, $result->outputs);
        $context = $context->withStepStatus($step->stepId, StepStatus::Succeeded);

        $this->continueFrom($workflow, $context, $executionId);
    }

    private function applyEnd(
        Transition $transition,
        Workflow $workflow,
        Step $step,
        WorkflowContext $context,
        string $executionId,
    ): void {
        $succeeded = ($transition->status ?? 'failed') === 'succeeded';

        // Do not auto-complete while a receiving correlation may still land.
        if ($succeeded && $this->pendingCorrelations->existsForExecution($executionId)) {
            $this->stateStore->save($executionId, $this->serialize($context), $this->stateTtlSeconds);

            return;
        }

        $status = $succeeded ? ExecutionStatus::Succeeded : ExecutionStatus::Failed;
        $this->terminate($context, $executionId, $status, $succeeded ? 'execution.succeeded' : 'execution.failed');

        if ($succeeded) {
            $this->events->dispatch(new RunCompletedEvent(
                $executionId,
                $workflow->workflowId,
                $this->stepOutputs($context, $step->stepId),
                new DateTimeImmutable(),
            ));
        } else {
            $this->events->dispatch(new RunFailedEvent(
                $executionId,
                $workflow->workflowId,
                new RuntimeException("Workflow '{$workflow->workflowId}' ended in failure at step '{$step->stepId}'"),
                new DateTimeImmutable(),
            ));
        }
    }

    /**
     * Continues execution after an inline sub-workflow invocation resolved:
     * computes the next runnable step and dispatches or completes.
     */
    private function continueFrom(Workflow $workflow, WorkflowContext $context, string $executionId): void
    {
        $this->stateStore->save($executionId, $this->serialize($context), $this->stateTtlSeconds);

        $graph = new DependencyGraph($workflow->steps);
        $runnable = new DependencyAnalyzer($graph)->getRunnableSteps($context);

        if ($runnable !== []) {
            foreach ($runnable as $runnableStep) {
                $this->queueDriver->dispatch(new ExecuteStepJob($runnableStep, $context));
            }

            return;
        }

        if (!$this->pendingCorrelations->existsForExecution($executionId)) {
            $this->terminate($context, $executionId, ExecutionStatus::Succeeded, 'execution.succeeded');
        }
    }

    private function findAction(ArazzoDocument $document, Workflow $workflow, Step $step, string $class): ?object
    {
        foreach ([$step->flow->onSuccess, $step->flow->onFailure, $workflow->successActions, $workflow->failureActions] as $list) {
            foreach ($list as $action) {
                if ($action instanceof $class) {
                    return $action;
                }
                if ($action instanceof Reusable) {
                    $resolved = str_contains($action->reference, 'failureActions')
                        ? ($document->components->failureActions[$this->referenceName($action->reference)] ?? null)
                        : ($document->components->successActions[$this->referenceName($action->reference)] ?? null);
                    if ($resolved instanceof $class) {
                        return $resolved;
                    }
                }
            }
        }

        return null;
    }

    private function referenceName(string $reference): string
    {
        return substr($reference, (int) strrpos($reference, '.') + 1);
    }

    private function stateFrom(WorkflowContext $context): ExecutionState
    {
        return ExecutionState::fromContext($context);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(WorkflowContext $context): array
    {
        return $context->toArray();
    }

    private function terminate(WorkflowContext $context, string $executionId, ExecutionStatus $status, string $eventType): void
    {
        $this->stateStore->save($executionId, $this->serialize($context), $this->stateTtlSeconds);
        $this->executionRegistry->complete($executionId, $status);
        $this->eventLedger->append($executionId, $eventType, ['workflowId' => $context->getWorkflowId()]);
    }

    private function findWorkflow(ArazzoDocument $document, string $workflowId): ?Workflow
    {
        return array_find($document->workflows, fn ($workflow) => $workflow->workflowId === $workflowId);
    }

    private function findStep(Workflow $workflow, string $stepId): ?Step
    {
        return array_find($workflow->steps, fn ($step) => $step->stepId === $stepId);
    }

    /**
     * Step outputs are a name-to-value map, so the keys are narrowed to
     * strings before the value reaches the event's typed constructor.
     *
     * @return array<string, mixed>
     */
    private function stepOutputs(WorkflowContext $context, string $stepId): array
    {
        $record = $context->getSteps()[$stepId] ?? null;
        $outputs = is_array($record) ? ($record['outputs'] ?? null) : null;

        if (!is_array($outputs)) {
            return [];
        }

        return array_filter($outputs, static fn (mixed $key): bool => is_string($key), ARRAY_FILTER_USE_KEY);
    }
}
