<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Dto\Action\FailureEndAction;
use Alama\Arazzo\Dto\Action\FailureGotoAction;
use Alama\Arazzo\Dto\Action\FailureAction;
use Alama\Arazzo\Dto\Action\RetryAction;
use Alama\Arazzo\Dto\Action\SubWorkflowFailureAction;
use Alama\Arazzo\Dto\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Dto\Action\SuccessAction;
use Alama\Arazzo\Dto\Action\SuccessEndAction;
use Alama\Arazzo\Dto\Action\SuccessGotoAction;
use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Reusable;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Runner\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Dto\ExecutionState;
use Alama\Arazzo\Runner\Dto\Transition;
use Alama\Arazzo\Runner\Exceptions\GotoTargetNotFoundException;
use Alama\Arazzo\Runner\Exceptions\StepBudgetExceededException;
use Alama\Arazzo\Runner\Exceptions\WorkflowCycleException;
use Alama\Arazzo\Runner\Exceptions\WorkflowDepthExceededException;

/** Chooses the next execution state. It intentionally knows nothing about queues, locks, storage, or events. */
final class WorkflowEngine
{
    public function __construct(private ExpressionResolverInterface $expressions, private int $maxRetryAttempts = 10) {}

    /**
     * Apply a completed attempt. The adapter is responsible for doing the actual
     * I/O and storing its result in $state before calling this method.
     */
    public function transition(ArazzoDocument $document, Workflow $workflow, Step $step, ExecutionState $state, bool $criteriaMet, bool $suspended = false): Transition
    {
        if ($suspended) {
            return Transition::suspend($state->withCurrentStep($step->stepId));
        }
        if ($state->stepsSpent >= $state->maxSteps) {
            throw new StepBudgetExceededException("Execution '{$state->executionId}' exceeded its {$state->maxSteps}-step budget.");
        }

        $state = $state->spendStep()->withCurrentStep($step->stepId);
        $record = $state->stepResults[$step->stepId] ?? [];
        $record['status'] = $criteriaMet ? StepStatus::Succeeded->value : StepStatus::Failed->value;
        $state = $state->withStepResult($step->stepId, $record);
        $actions = $this->actions($document, $workflow, $step, $criteriaMet);
        foreach ($actions as $position => $action) {
            if (!$this->expressions->evaluateCriteria($action->criteria, $step, $this->context($state), $document)) {
                continue;
            }
            if ($action instanceof RetryAction) {
                $limit = min($action->retryLimit ?? PHP_INT_MAX, $this->maxRetryAttempts);
                if ($state->attemptFor($step->stepId) >= $limit) {
                    continue;
                }
                $targetWorkflowId = $action->workflowId ?? $workflow->workflowId;
                $targetStepId = $action->stepId ?? $step->stepId;
                $this->target($document, $targetWorkflowId, $targetStepId);
                $next = $state->withStepAttempt($step->stepId)->withWorkflow($targetWorkflowId)->withCurrentStep($targetStepId);
                return Transition::retry($next, $targetStepId, $action->retryAfter ?? 0, $targetWorkflowId);
            }
            if ($action instanceof SuccessGotoAction || $action instanceof FailureGotoAction) {
                $targetWorkflowId = $action->workflowId ?? $workflow->workflowId;
                if ($action->stepId !== null) { $this->target($document, $targetWorkflowId, $action->stepId); }
                return Transition::goto($state->withWorkflow($targetWorkflowId)->withCurrentStep($action->stepId), $action->stepId, $targetWorkflowId);
            }
            if ($action instanceof SuccessEndAction || $action instanceof FailureEndAction) {
                return Transition::end($state, $action instanceof SuccessEndAction ? 'succeeded' : 'failed');
            }
            if ($action instanceof SubWorkflowSuccessAction || $action instanceof SubWorkflowFailureAction) {
                $this->assertCanEnter($state, $action->workflowId);
                // Invocation is deliberately represented as a transition. An adapter can run
                // the nested engine using the shared budget and stack before resuming here.
                return Transition::goto($state->enterWorkflow($action->workflowId), null, $action->workflowId);
            }
        }

        if (!$criteriaMet) {
            return Transition::end($state, 'failed');
        }
        $next = $this->nextRunnable($workflow, $state, $step->stepId);
        return $next === null ? Transition::end($state, 'succeeded') : Transition::next($state->withCurrentStep($next), $next);
    }

    /** @return list<SuccessAction|FailureAction> */
    private function actions(ArazzoDocument $document, Workflow $workflow, Step $step, bool $criteriaMet): array
    {
        $actions = $criteriaMet ? $step->onSuccess : $step->onFailure;
        $actions = $actions !== [] ? $actions : ($criteriaMet ? $workflow->successActions : $workflow->failureActions);
        $type = $criteriaMet ? 'successActions' : 'failureActions';
        return array_map(function (SuccessAction|FailureAction|Reusable $action) use ($document, $type): SuccessAction|FailureAction {
            if (!$action instanceof Reusable) { return $action; }
            $prefix = "\$components.{$type}.";
            if (!str_starts_with($action->reference, $prefix)) { throw new GotoTargetNotFoundException("Reusable reference '{$action->reference}' does not target components.{$type}."); }
            $name = substr($action->reference, strlen($prefix));
            $resolved = $type === 'successActions' ? ($document->components->successActions[$name] ?? null) : ($document->components->failureActions[$name] ?? null);
            if ($resolved === null) { throw new GotoTargetNotFoundException("Reusable reference '{$action->reference}' does not resolve."); }
            return $resolved;
        }, $actions);
    }

    private function nextRunnable(Workflow $workflow, ExecutionState $state, string $completed): ?string
    {
        foreach ($workflow->steps as $candidate) {
            if ($candidate->stepId === $completed || isset($state->stepResults[$candidate->stepId])) { continue; }
            $dependencies = $candidate->dependsOn;
            foreach ($dependencies as $dependency) {
                if (!isset($state->stepResults[$dependency])) { continue 2; }
            }
            return $candidate->stepId;
        }
        return null;
    }

    /** @return array{0: Workflow, 1: Step} */
    private function target(ArazzoDocument $document, string $workflowId, string $stepId): array
    {
        foreach ($document->workflows as $workflow) {
            if ($workflow->workflowId !== $workflowId) { continue; }
            foreach ($workflow->steps as $step) { if ($step->stepId === $stepId) { return [$workflow, $step]; } }
            break;
        }
        throw new GotoTargetNotFoundException("Action references unknown stepId '{$stepId}' in workflow '{$workflowId}'.");
    }

    private function assertCanEnter(ExecutionState $state, string $workflowId): void
    {
        if (in_array($workflowId, $state->workflowCallStack, true)) { throw new WorkflowCycleException("Workflow '{$workflowId}' is already on the execution call stack."); }
        if (count($state->workflowCallStack) >= $state->maxWorkflowDepth) { throw new WorkflowDepthExceededException("Execution exceeded workflow depth {$state->maxWorkflowDepth}."); }
    }

    private function context(ExecutionState $state): WorkflowContext
    {
        $steps = $state->stepResults;
        foreach ($state->stepAttempts as $id => $attempt) { $steps[$id]['attempts'] = $attempt; }
        return new WorkflowContext($state->definitionId, $state->inputs, $steps, $state->components, $state->workflowId, $state->executionId);
    }
}
