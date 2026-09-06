<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\Spec\Action\SubWorkflowFailureAction;
use Alama\Arazzo\Contracts\Spec\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Expression\ExpressionEngineInterface;
use Alama\Arazzo\Runner\Execution\Data\ExecutionEvaluationInput;
use Alama\Arazzo\Runner\Execution\Data\SubWorkflowResult;
use Alama\Arazzo\Runner\Execution\Exceptions\ExecutionException;
use Alama\Arazzo\Runner\State\Interfaces\DefinitionRegistryInterface;

class SubWorkflowInvoker
{
    public function __construct(
        private DefinitionRegistryInterface $registry,
        private WorkflowExecutor $executor,
        private ExpressionEngineInterface $engine,
    ) {}

    public function invoke(
        SubWorkflowSuccessAction|SubWorkflowFailureAction $action,
        WorkflowContext $parent,
    ): SubWorkflowResult {
        $document = $this->registry->get($parent->getDefinitionId());

        if ($document === null) {
            throw ExecutionException::subWorkflowNotFound($action->workflowId);
        }

        $target = null;
        foreach ($document->workflows as $w) {
            if ($w->workflowId === $action->workflowId) {
                $target = $w;
                break;
            }
        }

        if ($target === null) {
            throw ExecutionException::subWorkflowNotFound($action->workflowId);
        }

        $bound = array_map(function ($spec) use ($parent) {
            return match (true) {
                $spec instanceof Expression => $this->engine->evaluate($spec, new ExecutionEvaluationInput($parent, '__invoke__')),
                $spec instanceof Selector => $this->engine->evaluateSelector($spec, $parent, '__invoke__'),
                default => $spec,
            };
        }, $action->parameters);

        $child = WorkflowContext::forChildInvocation($parent, $target, $bound);
        $outcome = $this->executor->execute($target, $document, $bound, $child);

        return new SubWorkflowResult(
            outputs: $outcome->outputs,
            status: $outcome->status,
            childRunId: (string) $child->getExecutionId(),
            inputs: $bound,
            stepsSpent: $outcome->stepsSpent,
            workflowCallStack: $outcome->workflowCallStack,
        );
    }
}
