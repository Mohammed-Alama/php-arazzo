<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution;

use Alama\Arazzo\Evaluation\EvaluationContext;
use Alama\Arazzo\Exceptions\ExecutionException;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Expression\SelectorEvaluator;
use Alama\Arazzo\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Spec\Action\SubWorkflowFailureAction;
use Alama\Arazzo\Spec\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Selector;
use Alama\Arazzo\Spec\WorkflowContext;

class SubWorkflowInvoker
{
    public function __construct(
        private DefinitionRegistryInterface $registry,
        private WorkflowExecutor $executor,
        private ExpressionEvaluator $expressions,
        private SelectorEvaluator $selectors,
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

        $bound = [];
        foreach ($action->parameters as $name => $spec) {
            $bound[$name] = match (true) {
                $spec instanceof Expression => $this->expressions->evaluate($spec, new EvaluationContext($parent, '__invoke__')),
                $spec instanceof Selector => $this->selectors->evaluate($spec, $parent, '__invoke__'),
                default => $spec,
            };
        }

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
