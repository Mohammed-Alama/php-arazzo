<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Async;

use Alama\Arazzo\Contracts\EventLedgerInterface;
use Alama\Arazzo\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Contracts\QueueDriverInterface;
use Alama\Arazzo\Contracts\StateStoreInterface;
use Alama\Arazzo\Runner\Context\ExecutionState;
use Alama\Arazzo\Runner\Execution\Enum\TransitionType;
use Alama\Arazzo\Runner\Execution\ExecutionStatus;
use Alama\Arazzo\Runner\Execution\Transition;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Jobs\ExecuteStepJob;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;

/**
 * Performs the side effects a {@see Transition} describes: persists the next
 * context, completes or fails the execution when terminal, and dispatches
 * the follow-up job otherwise. All decisions were already made by the engine.
 */
final class TransitionApplier
{
    public function __construct(
        private readonly StateStoreInterface $stateStore,
        private readonly ExecutionRegistryInterface $executionRegistry,
        private readonly EventLedgerInterface $eventLedger,
        private readonly QueueDriverInterface $queueDriver,
        private readonly WorkflowEngine $workflowEngine,
        private readonly WorkerEvents $events,
        private readonly int $stateTtlSeconds = 86400,
    ) {}

    public const OUTCOME_CONTINUE = 'continue';

    public const OUTCOME_TERMINAL = 'terminal';

    public const OUTCOME_ABORTED = 'aborted';

    /**
     * Applies the side effects the transition describes.
     *
     * - OUTCOME_CONTINUE: run proceeds (caller should emit StepExecuted)
     * - OUTCOME_TERMINAL: run completed/failed cleanly (StepExecuted still
     *   emitted by the caller, matching the historical ordering)
     * - OUTCOME_ABORTED: follow-up pointed at a workflow missing from the
     *   document; the run was failed and NO further events fire
     */
    public function apply(ArazzoDocument $document, Workflow $workflow, Step $step, Transition $transition, string $executionId): string
    {
        // Persist in WorkflowContext shape so StateReconciler and
        // CorrelationResumer keep reading the same keys.
        $nextState = $transition->state;
        assert($nextState instanceof ExecutionState); // engine is canonical on ExecutionState
        $this->stateStore->save($executionId, $nextState->toContext()->toArray(), $this->stateTtlSeconds);

        if ($transition->isTerminal()) {
            $this->completeTerminal($document, $workflow, $step, $transition, $executionId);

            return self::OUTCOME_TERMINAL;
        }

        if ($transition->type !== TransitionType::Suspend) {
            return $this->dispatchFollowUp($document, $workflow, $transition, $executionId)
                ? self::OUTCOME_ABORTED
                : self::OUTCOME_CONTINUE;
        }

        return self::OUTCOME_CONTINUE; // suspension side effects live in SuspensionHandler
    }

    private function completeTerminal(ArazzoDocument $document, Workflow $workflow, Step $step, Transition $transition, string $executionId): void
    {
        $succeeded = $transition->status === 'succeeded';
        $finalWorkflowId = $transition->state->workflowId;

        $this->executionRegistry->complete(
            $executionId,
            $succeeded ? ExecutionStatus::Succeeded : ExecutionStatus::Failed,
        );
        $this->eventLedger->append(
            $executionId,
            $succeeded ? 'execution.succeeded' : 'execution.failed',
            ['workflowId' => $finalWorkflowId],
        );

        if ($succeeded) {
            $outputs = $this->workflowEngine->evaluateWorkflowOutputs($document, $workflow, $transition->state);
            $this->events->runCompleted($executionId, $finalWorkflowId, $outputs);

            return;
        }

        $this->events->runFailedBecause(
            $executionId,
            $finalWorkflowId,
            "Workflow '{$finalWorkflowId}' failed at step '{$step->stepId}'.",
        );
    }

    private function dispatchFollowUp(ArazzoDocument $document, Workflow $workflow, Transition $transition, string $executionId): bool
    {
        $targetWorkflowId = $transition->workflowId ?? $workflow->workflowId;
        $targetWorkflow = $this->findWorkflow($document, $targetWorkflowId);

        if ($targetWorkflow === null) {
            // An invoke/goto pointed at a workflow that does not exist in the
            // document: fail the run cleanly instead of dispatching into the void.
            $this->eventLedger->append($executionId, 'execution.workflow_missing', ['workflowId' => $targetWorkflowId]);
            $this->events->runFailedBecause($executionId, $targetWorkflowId, "Unknown workflow '{$targetWorkflowId}'.");

            return true;
        }

        $targetStep = $transition->stepId !== null
            ? $this->findStep($targetWorkflow, $transition->stepId)
            : ($targetWorkflow->steps[0] ?? null);

        if ($targetStep !== null) {
            $dispatchState = $transition->state;
            assert($dispatchState instanceof ExecutionState);
            $this->queueDriver->dispatch(new ExecuteStepJob($targetStep, $dispatchState->toContext()), $transition->delaySeconds);
        }

        return false;
    }

    private function findWorkflow(ArazzoDocument $document, string $workflowId): ?Workflow
    {
        foreach ($document->workflows as $candidate) {
            if ($candidate->workflowId === $workflowId) {
                return $candidate;
            }
        }

        return null;
    }

    private function findStep(Workflow $workflow, string $stepId): ?Step
    {
        foreach ($workflow->steps as $candidate) {
            if ($candidate->stepId === $stepId) {
                return $candidate;
            }
        }

        return null;
    }
}
