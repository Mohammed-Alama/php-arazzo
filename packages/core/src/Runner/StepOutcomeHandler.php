<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Dto\Action\FailureAction;
use Alama\Arazzo\Dto\Action\FailureEndAction;
use Alama\Arazzo\Dto\Action\FailureGotoAction;
use Alama\Arazzo\Dto\Action\RetryAction;
use Alama\Arazzo\Dto\Action\SubWorkflowFailureAction;
use Alama\Arazzo\Dto\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Dto\Action\SuccessAction;
use Alama\Arazzo\Dto\Action\SuccessEndAction;
use Alama\Arazzo\Dto\Action\SuccessGotoAction;
use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\Reusable;
use Alama\Arazzo\Dto\Selector;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Events\Dispatcher\NullEventDispatcher;
use Alama\Arazzo\Events\RunCompleted;
use Alama\Arazzo\Events\RunFailed;
use Alama\Arazzo\Events\StepRetried;
use Alama\Arazzo\Resolver\SelectorEvaluator;
use Alama\Arazzo\Runner\Contracts\EventLedgerInterface;
use Alama\Arazzo\Runner\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\Contracts\QueueDriverInterface;
use Alama\Arazzo\Runner\Contracts\StateStoreInterface;
use Alama\Arazzo\Runner\Exceptions\GotoTargetNotFoundException;
use Alama\Arazzo\Runner\Jobs\ExecuteStepJob;
use LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;

class StepOutcomeHandler
{
    private EventDispatcherInterface $events;

    public function __construct(
        private QueueDriverInterface $queueDriver,
        private Engine $engine,
        private ExecutionRegistryInterface $executionRegistry,
        private EventLedgerInterface $eventLedger,
        private PendingCorrelationRegistryInterface $pendingCorrelations,
        private ExpressionResolverInterface $expressionResolver,
        private StateStoreInterface $stateStore,
        private SubWorkflowInvoker $invoker,
        private SelectorEvaluator $selectors,
        private ExpressionEvaluator $expressions,
        private int $maxRetryAttempts = 10,
        private int $stateTtlSeconds = 86400,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new NullEventDispatcher();
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
                $value instanceof Expression => $this->expressions->evaluate($value, $context, $step->stepId),
                default => $value,
            };
            $context = $context->withStepOutput($step->stepId, $name, $resolved);
        }

        $actions = $this->resolveActionList($document, $workflow, $step, $criteriaMet);

        $this->applyFirstMatch($actions, $document, $workflow, $step, $context, $executionId, $criteriaMet);
    }

    /**
     * @param list<SuccessAction|FailureAction> $actions
     */
    private function applyFirstMatch(
        array $actions,
        ArazzoDocument $document,
        Workflow $workflow,
        Step $step,
        WorkflowContext $context,
        string $executionId,
        bool $criteriaMet,
    ): void {
        $matched = $this->firstMatchingAction($actions, $step, $context, $document);

        if ($matched === null) {
            if ($criteriaMet) {
                $this->continueNormally($workflow, $step, $context, $executionId);
            } else {
                $this->terminate($context, $executionId, ExecutionStatus::Failed, 'execution.failed');
            }

            return;
        }

        if ($matched instanceof RetryAction) {
            $this->handleRetry($matched, $actions, $document, $workflow, $step, $context, $executionId, $criteriaMet);

            return;
        }

        if ($matched instanceof SuccessGotoAction || $matched instanceof FailureGotoAction) {
            $status = $matched instanceof SuccessGotoAction ? StepStatus::Succeeded : StepStatus::Failed;
            $newContext = $context->withStepStatus($step->stepId, $status);
            $this->handleGoto($matched, $document, $newContext, $executionId);

            return;
        }

        if ($matched instanceof SuccessEndAction || $matched instanceof FailureEndAction) {
            $status = $matched instanceof SuccessEndAction ? ExecutionStatus::Succeeded : ExecutionStatus::Failed;
            $this->terminate(
                $context,
                $executionId,
                $status,
                $status === ExecutionStatus::Succeeded ? 'execution.succeeded' : 'execution.failed',
            );

            if ($matched instanceof SuccessEndAction) {
                $this->events->dispatch(new RunCompleted(
                    $executionId,
                    $workflow->workflowId,
                    $context->getSteps()[$step->stepId]['outputs'] ?? [],
                    new \DateTimeImmutable(),
                ));
            } else {
                $this->events->dispatch(new RunFailed(
                    $executionId,
                    $workflow->workflowId,
                    new \RuntimeException("Workflow '{$workflow->workflowId}' ended in failure at step '{$step->stepId}'"),
                    new \DateTimeImmutable(),
                ));
            }

            return;
        }

        if ($matched instanceof SubWorkflowSuccessAction || $matched instanceof SubWorkflowFailureAction) {
            $result = $this->invoker->invoke($matched, $context);
            $context = $context->withStepOutput($step->stepId, $matched->name, $result->outputs);
            $this->continueNormally($workflow, $step, $context, $executionId);

            return;
        }

        throw new LogicException('Unhandled action type: ' . $matched::class);
    }

    /**
     * @return list<SuccessAction|FailureAction>
     */
    private function resolveActionList(ArazzoDocument $document, Workflow $workflow, Step $step, bool $criteriaMet): array
    {
        $stepList = $criteriaMet ? $step->onSuccess : $step->onFailure;
        $list = $stepList !== [] ? $stepList : ($criteriaMet ? $workflow->successActions : $workflow->failureActions);
        $componentType = $criteriaMet ? 'successActions' : 'failureActions';

        return array_map(fn ($action) => $this->resolveReusable($action, $document, $componentType), $list);
    }

    private function resolveReusable(SuccessAction|FailureAction|Reusable $action, ArazzoDocument $document, string $componentType): SuccessAction|FailureAction
    {
        if (!$action instanceof Reusable) {
            return $action;
        }

        $prefix = "\$components.{$componentType}.";
        if (!str_starts_with($action->reference, $prefix)) {
            throw new GotoTargetNotFoundException("Reusable reference '{$action->reference}' does not target components.{$componentType}.");
        }

        $name = substr($action->reference, strlen($prefix));
        $resolved = $componentType === 'successActions'
            ? ($document->components->successActions[$name] ?? null)
            : ($document->components->failureActions[$name] ?? null);

        if ($resolved === null) {
            throw new GotoTargetNotFoundException("Reusable reference '{$action->reference}' does not resolve.");
        }

        return $resolved;
    }

    /**
     * @param list<SuccessAction|FailureAction> $actions
     */
    private function firstMatchingAction(array $actions, Step $step, WorkflowContext $context, ArazzoDocument $document): SuccessAction|FailureAction|null
    {
        foreach ($actions as $action) {
            if ($this->expressionResolver->evaluateCriteria($action->criteria, $step, $context, $document)) {
                return $action;
            }
        }

        return null;
    }

    /**
     * @param list<SuccessAction|FailureAction> $actionsConsidered
     */
    private function handleRetry(
        RetryAction $action,
        array $actionsConsidered,
        ArazzoDocument $document,
        Workflow $workflow,
        Step $step,
        WorkflowContext $context,
        string $executionId,
        bool $criteriaMet,
    ): void {
        $attempts = $context->getStepAttempts($step->stepId);
        $limit = min($action->retryLimit ?? PHP_INT_MAX, $this->maxRetryAttempts);

        if ($attempts >= $limit) {
            $this->eventLedger->append($executionId, 'step.retry_exhausted', [
                'stepId' => $step->stepId,
                'attempts' => $attempts,
            ]);

            $index = array_search($action, $actionsConsidered, true);
            $remaining = array_slice($actionsConsidered, $index + 1);
            $this->applyFirstMatch($remaining, $document, $workflow, $step, $context, $executionId, $criteriaMet);

            return;
        }

        $targetStepId = $action->stepId ?? $step->stepId;
        $targetWorkflowId = $action->workflowId ?? $workflow->workflowId;
        [$targetWorkflow, $targetStep] = $this->resolveTarget($document, $targetWorkflowId, $targetStepId);

        $newContext = $context
            ->withStepAttemptIncremented($step->stepId)
            ->withStepStatus($step->stepId, StepStatus::Retrying);

        if ($targetWorkflow->workflowId !== $context->getWorkflowId()) {
            $newContext = $newContext->withWorkflowId($targetWorkflow->workflowId);
        }
        if ($targetStep->stepId !== $step->stepId) {
            $newContext = $newContext->withStepStatus($targetStep->stepId, StepStatus::Pending);
        }

        $this->stateStore->save($executionId, $this->serialize($newContext), $this->stateTtlSeconds);

        $attempt = $context->getStepAttempts($step->stepId);
        $this->events->dispatch(new StepRetried(
            $executionId,
            $workflow->workflowId,
            $step->stepId,
            $attempt,
            null,
            new \DateTimeImmutable(),
        ));

        $this->queueDriver->dispatch(new ExecuteStepJob($targetStep, $newContext), $action->retryAfter ?? 0);
    }

    private function handleGoto(SuccessGotoAction|FailureGotoAction $action, ArazzoDocument $document, WorkflowContext $context, string $executionId): void
    {
        $targetWorkflowId = $action->workflowId ?? $context->getWorkflowId();
        $targetWorkflow = $this->findWorkflow($document, (string) $targetWorkflowId);
        if ($targetWorkflow === null) {
            throw new GotoTargetNotFoundException("Goto action '{$action->name}' references unknown workflowId '{$targetWorkflowId}'.");
        }

        $newContext = $targetWorkflow->workflowId !== $context->getWorkflowId()
            ? $context->withWorkflowId($targetWorkflow->workflowId)
            : $context;

        if ($action->stepId === null) {
            // No specific step named -- transfer to the target workflow's start, letting
            // normal dependency-driven choreography pick its entry steps.
            $this->stateStore->save($executionId, $this->serialize($newContext), $this->stateTtlSeconds);
            $this->engine->evaluate($targetWorkflow, $newContext);

            return;
        }

        $targetStep = $this->findStep($targetWorkflow, $action->stepId);
        if ($targetStep === null) {
            throw new GotoTargetNotFoundException("Goto action '{$action->name}' references unknown stepId '{$action->stepId}' in workflow '{$targetWorkflow->workflowId}'.");
        }

        $newContext = $newContext->withStepStatus($targetStep->stepId, StepStatus::Pending);

        $this->stateStore->save($executionId, $this->serialize($newContext), $this->stateTtlSeconds);
        $this->queueDriver->dispatch(new ExecuteStepJob($targetStep, $newContext));
    }

    /**
     * @return array{0: Workflow, 1: Step}
     */
    private function resolveTarget(ArazzoDocument $document, string $workflowId, string $stepId): array
    {
        $workflow = $this->findWorkflow($document, $workflowId);
        if ($workflow === null) {
            throw new GotoTargetNotFoundException("Action references unknown workflowId '{$workflowId}'.");
        }

        $step = $this->findStep($workflow, $stepId);
        if ($step === null) {
            throw new GotoTargetNotFoundException("Action references unknown stepId '{$stepId}' in workflow '{$workflow->workflowId}'.");
        }

        return [$workflow, $step];
    }

    private function findWorkflow(ArazzoDocument $document, string $workflowId): ?Workflow
    {
        foreach ($document->workflows as $workflow) {
            if ($workflow->workflowId === $workflowId) {
                return $workflow;
            }
        }

        return null;
    }

    private function findStep(Workflow $workflow, string $stepId): ?Step
    {
        foreach ($workflow->steps as $step) {
            if ($step->stepId === $stepId) {
                return $step;
            }
        }

        return null;
    }

    private function continueNormally(Workflow $workflow, Step $step, WorkflowContext $context, string $executionId): void
    {
        $newContext = $context->withStepStatus($step->stepId, StepStatus::Succeeded);

        $this->stateStore->save($executionId, $this->serialize($newContext), $this->stateTtlSeconds);
        $this->engine->evaluate($workflow, $newContext);

        $graph = new DependencyGraph($workflow->steps);
        $analyzer = new DependencyAnalyzer($graph);
        $runnable = $analyzer->getRunnableSteps($newContext);
        if ($runnable === [] && !$this->pendingCorrelations->existsForExecution($executionId)) {
            $this->terminate($newContext, $executionId, ExecutionStatus::Succeeded, 'execution.succeeded');
        }
    }

    private function terminate(WorkflowContext $context, string $executionId, ExecutionStatus $status, string $eventType): void
    {
        $this->stateStore->save($executionId, $this->serialize($context), $this->stateTtlSeconds);
        $this->executionRegistry->complete($executionId, $status);
        $this->eventLedger->append($executionId, $eventType, ['workflowId' => $context->getWorkflowId()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(WorkflowContext $context): array
    {
        return [
            'definitionId' => $context->getDefinitionId(),
            'workflowId' => $context->getWorkflowId(),
            'steps' => $context->getSteps(),
            'inputs' => $context->getInputs(),
            'components' => $context->getComponents(),
        ];
    }
}
