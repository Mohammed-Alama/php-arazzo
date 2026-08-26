<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\EvaluationContext;
use Alama\Arazzo\Runner\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Runner\Exceptions\ExecutionException;
use Alama\Arazzo\Runner\Execution\Contracts\StepProtocolExecutorInterface;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;

/**
 * Executes steps that target a nested workflow via workflowId. The child
 * workflow runs in its own child context; its outputs are surfaced as the
 * step's outputs so parent expressions like {$steps.s.outputs.x} resolve.
 */
final class SubWorkflowStepExecutor implements StepProtocolExecutorInterface
{
    public function __construct(
        private WorkflowExecutor $executor,
        private ExpressionEvaluator $evaluator,
    ) {}

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return $step->workflowId !== null && !in_array($step->action, ['send', 'receive'], true);
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        $target = null;
        foreach ($document->workflows as $workflow) {
            if ($workflow->workflowId === $step->workflowId) {
                $target = $workflow;
                break;
            }
        }

        if ($target === null) {
            throw ExecutionException::subWorkflowNotFound((string) $step->workflowId);
        }

        $evaluationContext = new EvaluationContext($context, $step->stepId, $document);

        $bound = [];
        $parameters = (new ReusableParameterResolver())->resolve($step->parameters, $document);

        foreach ($parameters as $parameter) {
            $bound[$parameter->name] = $parameter->value instanceof Expression
                ? $this->evaluator->evaluate($parameter->value, $evaluationContext)
                : $parameter->value;
        }

        $child = WorkflowContext::forChildInvocation($context, $target, $bound);
        $result = $this->executor->execute($target, $document, $bound, $child);

        // A completed sub-workflow has no HTTP semantics; 200 marks success.
        return StepExecutionOutcome::resolved(200, $result->outputs, [], $bound, [
            'method' => 'WORKFLOW',
            'url' => '#workflows/'.$target->workflowId,
        ]);
    }
}
