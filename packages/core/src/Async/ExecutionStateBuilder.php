<?php

declare(strict_types=1);

namespace Alama\Arazzo\Async;

use Alama\Arazzo\Execution\Data\WorkflowContext;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\State\ExecutionState;

/**
 * Assembles the engine-ready {@see ExecutionState} for a transition decision:
 * seeds from the persisted payload when present, folds every completed step
 * record in, restores the shared budget/call-stack, and replays attempt
 * counters.
 *
 * The attempt replay is deliberately off by one: a persisted 'attempts' value
 * is the 1-based number of the attempt that just ran, while the engine must
 * see the count of PREVIOUS attempts — matching the sync loop's decision-time
 * semantics.
 */
final class ExecutionStateBuilder
{
    /**
     * @param  array<string, mixed>|null  $persisted  raw state-store payload for this execution
     */
    public function build(?array $persisted, WorkflowContext $resultContext, Workflow $workflow, ?Step $overlayStep = null): ExecutionState
    {
        $workflowId = $workflow->workflowId;

        $state = $persisted !== null && isset($persisted['executionId'])
            ? ExecutionState::fromArray($persisted)
            : ExecutionState::start(
                (string) $resultContext->getExecutionId(),
                $resultContext->getDefinitionId(),
                $workflowId,
                $resultContext->getInputs(),
                components: $resultContext->getComponents(),
            );

        foreach ($resultContext->getSteps() as $completedStepId => $completedResult) {
            if (!is_array($completedResult)) {
                continue;
            }
            // Normalize to string-keyed records so persisted payloads of any
            // shape satisfy the engine's step-result contract.
            $record = [];
            foreach ($completedResult as $key => $value) {
                if (is_string($key)) {
                    $record[$key] = $value;
                }
            }
            $state = $state->withStepResult($completedStepId, $record);
        }

        $callStack = $resultContext->getWorkflowCallStack();

        $state = $state->restoreBudget(
            $resultContext->getStepsSpent(),
            $callStack !== [] ? $callStack : [$workflowId],
        );

        foreach ($this->attemptCounters($resultContext) as $attemptedStepId => $attempts) {
            while ($state->attemptFor($attemptedStepId) < $attempts - 1) {
                $state = $state->withStepAttempt($attemptedStepId);
            }
        }

        // The attempt that JUST ran lands last so the engine decides on it.
        if ($overlayStep !== null) {
            foreach ($this->stringKeyedRecords($resultContext, [$overlayStep->stepId]) as $stepId => $record) {
                $state = $state->withStepResult($stepId, $record);
            }
        }

        return $state;
    }

    /**
     * @param  list<string>  $stepIds
     * @return array<string, array<string, mixed>>
     */
    private function stringKeyedRecords(WorkflowContext $context, array $stepIds): array
    {
        $records = [];
        foreach ($stepIds as $stepId) {
            $raw = $context->getSteps()[$stepId] ?? null;
            if (!is_array($raw)) {
                continue;
            }
            $record = [];
            foreach ($raw as $key => $value) {
                if (is_string($key)) {
                    $record[$key] = $value;
                }
            }
            $records[$stepId] = $record;
        }

        return $records;
    }

    /** @return array<string, int> */
    private function attemptCounters(WorkflowContext $context): array
    {
        $attempts = [];
        foreach ($context->getSteps() as $id => $step) {
            if (!is_array($step)) {
                continue;
            }
            $counted = $step['attempts'] ?? 0;
            $attempts[$id] = is_int($counted) ? $counted : 0;
        }

        return $attempts;
    }
}
