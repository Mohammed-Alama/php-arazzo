<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol;

use Alama\Arazzo\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Contracts\StepProtocolExecutorInterface;
use Alama\Arazzo\Runner\Context\ExecutionState;
use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\DependencyGraph;
use Alama\Arazzo\Runner\Execution\Enum\TransitionType;
use Alama\Arazzo\Runner\Execution\StepExecutionOutcome;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Throwable;

/**
 * Runs a child workflow inline for high-frequency `invoke` steps, drawing
 * from the caller's shared budget via a child ExecutionState seeded with the
 * parent's spend and call stack.
 */
final class SubWorkflowExecutor implements StepProtocolExecutorInterface
{
    public function __construct(
        private WorkflowEngine $workflowEngine,
        private ExpressionResolverInterface $expressionResolver,
    ) {}

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return $step->workflowId !== null && $step->operationPath === null && $step->operationId === null;
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        $targetWorkflowId = (string) $step->workflowId;
        $targetWorkflow = $this->findWorkflow($document, $targetWorkflowId);

        if ($targetWorkflow === null) {
            return StepExecutionOutcome::resolved(0, [], [], request: ['workflowId' => $targetWorkflowId], failureCategory: 'execution');
        }

        $inputs = $this->resolveInputs($step, $context);

        // Child shares the parent's budget consumption and call-stack depth.
        $childState = ExecutionState::start(
            $executionId,
            $context->getDefinitionId(),
            $targetWorkflowId,
            $inputs,
            components: $context->getComponents(),
            stepsSpent: $context->getStepsSpent(),
            workflowCallStack: [...$context->getWorkflowCallStack(), $targetWorkflowId],
        );

        $firstStep = $targetWorkflow->steps[0] ?? null;
        $childEngineState = $childState;

        while ($firstStep instanceof Step) {
            $criteriaMet = $this->expressionResolver->evaluateSuccessCriteria($firstStep, $context, $document);
            $transition = $this->workflowEngine->transition($document, $targetWorkflow, $firstStep, $childState, $criteriaMet);
            $childEngineState = $transition->state;
            assert($childEngineState instanceof ExecutionState); // engine is canonical on ExecutionState
            $childState = $childEngineState;

            if ($transition->isTerminal()) {
                break;
            }

            if ($transition->type === TransitionType::Suspend) {
                // A suspended child cannot block an inline invocation; treat as failed.
                return StepExecutionOutcome::resolved(0, [], [], inputs: $inputs, request: ['workflowId' => $targetWorkflowId], failureCategory: 'execution');
            }

            $nextId = $transition->stepId ?? $this->nextRunnableId($targetWorkflow, $childEngineState, $firstStep->stepId);
            $firstStep = $nextId !== null ? $this->findStep($targetWorkflow, $nextId) : null;
        }

        try {
            $outputs = $this->workflowEngine->evaluateWorkflowOutputs($document, $targetWorkflow, $childEngineState);
        } catch (Throwable) {
            $outputs = [];
        }

        $rawBody = json_encode($outputs) ?: '{}';

        return StepExecutionOutcome::resolved(200, $outputs, $outputs, inputs: $inputs, request: ['workflowId' => $targetWorkflowId], rawBody: $rawBody, contentType: 'application/json');
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveInputs(Step $step, WorkflowContext $context): array
    {
        $inputs = [];
        foreach ($step->parameters as $parameter) {
            if (!property_exists($parameter, 'name')) {
                continue; // component defaults resolve inside the child scope
            }
            $value = $parameter->value instanceof Expression
                ? $this->expressionResolver->evaluate($parameter->value, $context, $step->stepId)
                : $parameter->value;
            $inputs[$parameter->name] = $value;
        }

        return $inputs;
    }

    private function nextRunnableId(Workflow $workflow, ExecutionState $state, string $completed): ?string
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
}
