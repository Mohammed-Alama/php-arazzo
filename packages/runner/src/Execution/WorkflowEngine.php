<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\Dependency\DependencyGraph;
use Alama\Arazzo\Contracts\Spec\Action\FailureAction;
use Alama\Arazzo\Contracts\Spec\Action\FailureEndAction;
use Alama\Arazzo\Contracts\Spec\Action\FailureGotoAction;
use Alama\Arazzo\Contracts\Spec\Action\RetryAction;
use Alama\Arazzo\Contracts\Spec\Action\SubWorkflowFailureAction;
use Alama\Arazzo\Contracts\Spec\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessEndAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessGotoAction;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\StepStatus;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Execution\Data\Transition;
use Alama\Arazzo\Runner\Execution\Exceptions\GotoTargetNotFoundException;
use Alama\Arazzo\Runner\Execution\Exceptions\StepBudgetExceededException;
use Alama\Arazzo\Runner\Execution\Exceptions\WorkflowCycleException;
use Alama\Arazzo\Runner\Execution\Exceptions\WorkflowDepthExceededException;
use Alama\Arazzo\Runner\Policy\RetryPolicy;
use Alama\Arazzo\Runner\State\Data\ExecutionContext;

/** Chooses the next execution state. It intentionally knows nothing about queues, locks, storage, or events. */
final class WorkflowEngine
{
    private RetryPolicy $retryPolicy;

    /**
     * @param  int|RetryPolicy|null  $maxRetryAttempts  Accepts the legacy int ceiling,
     *                                                  a full RetryPolicy, or null for defaults — keeping positional and
     *                                                  named-argument call sites (`maxRetryAttempts:`) source-compatible.
     * @param  float  $retryBackoffMultiplier  Legacy scalar tuning, honored when no
     *                                         explicit RetryPolicy is supplied.
     */
    public function __construct(
        private ExpressionResolverInterface $expressions,
        int|null|RetryPolicy $maxRetryAttempts = null,
        private float $retryBackoffMultiplier = 1.0,
    ) {
        $this->retryPolicy = $maxRetryAttempts instanceof RetryPolicy
            ? $maxRetryAttempts
            : new RetryPolicy(maxAttempts: $maxRetryAttempts ?? 10, backoffMultiplier: $retryBackoffMultiplier);
    }

    /**
     * Apply a completed attempt. The adapter is responsible for doing the actual
     * I/O and storing its result in $state before calling this method.
     */
    public function transition(ArazzoDocument $document, Workflow $workflow, Step $step, ExecutionState|ExecutionContext $incoming, bool $criteriaMet, bool $suspended = false): Transition
    {
        // The engine stays canonical on ExecutionState; the richer
        // ExecutionContext facade normalizes into it at the boundary.
        $state = $incoming instanceof ExecutionContext ? $incoming->toExecutionState() : $incoming;

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
            if (!$this->expressions->evaluateCriteria($action->criteria, $step, $state->toContext(), $document)) {
                continue;
            }
            if ($action instanceof RetryAction) {
                $attemptsSoFar = $state->attemptFor($step->stepId);
                if ($this->retryPolicy->isExhausted($attemptsSoFar, $action->retryLimit)) {
                    // Observable exhaustion marker; adapters may surface it.
                    $state = $state->withErrorEntry([
                        'type' => 'retry_exhausted',
                        'stepId' => $step->stepId,
                        'attempts' => $attemptsSoFar,
                    ]);

                    continue; // exhausted - fall through to later actions
                }
                $targetWorkflowId = $action->workflowId ?? $workflow->workflowId;
                $targetStepId = $action->stepId ?? $step->stepId;
                $this->target($document, $targetWorkflowId, $targetStepId);
                $next = $state->withStepAttempt($step->stepId)->withWorkflow($targetWorkflowId)->withCurrentStep($targetStepId);

                return Transition::retry($next, $targetStepId, $this->retryPolicy->calculateDelay($action, $step, $state->toContext(), $attemptsSoFar + 1), $targetWorkflowId);
            }
            if ($action instanceof SuccessGotoAction || $action instanceof FailureGotoAction) {
                $targetWorkflowId = $action->workflowId ?? $workflow->workflowId;
                if ($this->findWorkflow($document, $targetWorkflowId) === null) {
                    throw new GotoTargetNotFoundException("Goto action '{$action->name}' references unknown workflowId '{$targetWorkflowId}'.");
                }
                if ($action->stepId !== null) {
                    $this->target($document, $targetWorkflowId, $action->stepId);
                }

                $gotoState = $state->withWorkflow($targetWorkflowId)->withCurrentStep($action->stepId);

                // 1.1: goto parameters bind values into the target workflow input scope.
                foreach ($action->parameters as $parameter) {
                    if ($parameter instanceof Reusable) {
                        continue; // component defaults resolve inside the target scope
                    }

                    $value = $parameter->value instanceof Expression
                        ? $this->expressions->evaluate($parameter->value, $gotoState->toContext(), $step->stepId)
                        : $parameter->value;

                    $inputs = $gotoState->inputs;
                    $inputs[$parameter->name] = $value;
                    /** @var ExecutionState $gotoState */
                    $gotoState = $gotoState->withInputs($inputs);
                }

                return Transition::goto($gotoState, $action->stepId, $targetWorkflowId);
            }
            if ($action instanceof SuccessEndAction || $action instanceof FailureEndAction) {
                return Transition::end($state, $action instanceof SuccessEndAction ? 'succeeded' : 'failed');
            }
            if ($action instanceof SubWorkflowSuccessAction || $action instanceof SubWorkflowFailureAction) {
                $this->assertCanEnter($state, $action->workflowId);

                // Invocation is deliberately represented as a transition. An adapter can run
                // the nested engine using the shared budget and stack before resuming here.
                return Transition::invoke($state, $action->workflowId);
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
            if (!$action instanceof Reusable) {
                return $action;
            }
            $prefix = "\$components.{$type}.";
            if (!str_starts_with($action->reference, $prefix)) {
                throw new GotoTargetNotFoundException("Reusable reference '{$action->reference}' does not target components.{$type}.");
            }
            $name = substr($action->reference, strlen($prefix));
            $resolved = $type === 'successActions' ? ($document->components->successActions[$name] ?? null) : ($document->components->failureActions[$name] ?? null);
            if ($resolved === null) {
                throw new GotoTargetNotFoundException("Reusable reference '{$action->reference}' does not resolve.");
            }

            return $resolved;
        }, $actions);
    }

    /** @return array{0: Workflow, 1: Step} */
    private function target(ArazzoDocument $document, string $workflowId, string $stepId): array
    {
        foreach ($document->workflows as $workflow) {
            if ($workflow->workflowId !== $workflowId) {
                continue;
            }
            foreach ($workflow->steps as $step) {
                if ($step->stepId === $stepId) {
                    return [$workflow, $step];
                }
            }
            break;
        }
        throw new GotoTargetNotFoundException("Action references unknown stepId '{$stepId}' in workflow '{$workflowId}'.");
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

    private function assertCanEnter(ExecutionState $state, string $workflowId): void
    {
        if (in_array($workflowId, $state->workflowCallStack, true)) {
            throw new WorkflowCycleException("Workflow '{$workflowId}' is already on the execution call stack.");
        }
        if (count($state->workflowCallStack) >= $state->maxWorkflowDepth) {
            throw new WorkflowDepthExceededException("Execution exceeded workflow depth {$state->maxWorkflowDepth}.");
        }
    }

    private function nextRunnable(Workflow $workflow, ExecutionState $state, string $completed): ?string
    {
        $graph = new DependencyGraph($workflow->steps);

        foreach ($graph->getTopologicalOrder() as $candidateId) {
            if ($candidateId === $completed || isset($state->stepResults[$candidateId])) {
                continue;
            }

            foreach ($graph->getEffectiveDependencies($candidateId) as $dependency) {
                if (!isset($state->stepResults[$dependency])) {
                    continue 2;
                }
            }

            return $candidateId;
        }

        return null;
    }

    /**
     * Evaluates the workflow-level `outputs` expressions against the final
     * step results. Unresolvable expressions evaluate to null instead of
     * failing the run.
     *
     * @return array<string, mixed>
     */
    public function evaluateWorkflowOutputs(ArazzoDocument $document, Workflow $workflow, ExecutionState|ExecutionContext $state): array
    {
        if ($workflow->outputs === []) {
            return [];
        }

        $context = $state instanceof ExecutionContext ? $state->toWorkflowContext() : $state->toContext();

        $outputs = [];

        foreach ($workflow->outputs as $name => $expression) {
            try {
                $outputs[$name] = $expression instanceof Expression
                    ? $this->expressions->evaluate($expression, $context)
                    : $expression;
            } catch (\Throwable) {
                $outputs[$name] = null;
            }
        }

        return $outputs;
    }

    /**
     * Picks the next runnable step id for adapter-side loops that drive one
     * step per unit (queue jobs, CLI ticks). Public because adapters own the
     * driving loop; the decision itself stays here.
     */
    public function nextRunnableStep(Workflow $workflow, ExecutionState $state, ?string $completed): ?string
    {
        return $this->nextRunnable($workflow, $state, $completed ?? '');
    }
}
