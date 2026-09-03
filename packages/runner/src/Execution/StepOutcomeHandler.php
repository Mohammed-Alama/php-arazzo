<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\Dependency\DependencyAnalyzer;
use Alama\Arazzo\Contracts\Dependency\DependencyGraph;
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
use Alama\Arazzo\Expression\Evaluation\Data\EvaluationContext;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Expression\SelectorEvaluator;
use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\Events\RunCompletedEvent;
use Alama\Arazzo\Runner\Events\RunFailedEvent;
use Alama\Arazzo\Runner\Events\StepRetriedEvent;
use Alama\Arazzo\Runner\Execution\Data\RunControlFlow;
use Alama\Arazzo\Runner\Execution\Data\RunPersistence;
use Alama\Arazzo\Runner\Execution\Data\Transition;
use Alama\Arazzo\Runner\Execution\Enum\TransitionType;
use Alama\Arazzo\Runner\Jobs\ExecuteStepJob;
use Alama\Arazzo\Runner\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\StateStoreInterface;
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
        private SelectorEvaluator $selectors,
        private ExpressionEvaluator $expressions,
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
    ): void {
        foreach ($step->outputs as $name => $value) {
            $resolved = match (true) {
                $value instanceof Selector => $this->selectors->evaluate($value, $context, $step->stepId),
                $value instanceof Expression => $this->expressions->evaluate($value, new EvaluationContext($context, $step->stepId)),
                default => $value,
            };
            $context = $context->withStepOutput($step->stepId, $name, $resolved);
        }

        $state = $this->stateFrom($context);
        $transition = $this->workflowEngine->transition($document, $workflow, $step, $state, $criteriaMet);

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
            TransitionType::Goto => $this->applyGoto($transition, $document, $workflow, $step, $context, $executionId, $criteriaMet),
            TransitionType::Invoke => $this->applyInvoke($document, $workflow, $step, $context, $executionId),
            TransitionType::End => $this->applyEnd($transition, $workflow, $step, $context, $executionId),
            TransitionType::Suspend => null, // suspension handled by the executor layer
        };
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
        Workflow $workflow,
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

        $this->continueFrom($document, $workflow, $step, $context, $executionId);
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
                $context->getSteps()[$step->stepId]['outputs'] ?? [],
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
    private function continueFrom(ArazzoDocument $document, Workflow $workflow, Step $step, WorkflowContext $context, string $executionId): void
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
        foreach ([$step->onSuccess, $step->onFailure, $workflow->successActions, $workflow->failureActions] as $list) {
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
}
