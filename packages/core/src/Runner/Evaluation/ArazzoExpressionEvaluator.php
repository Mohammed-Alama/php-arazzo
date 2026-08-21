<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionEvaluatorInterface;
use Alama\Arazzo\Spec\Expression;

class ArazzoExpressionEvaluator implements ExpressionEvaluatorInterface
{
    public function __construct(private ExpressionEvaluator $evaluator)
    {
    }

    public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed
    {
        return $this->evaluator->evaluate($expression, $context, $currentStepId);
    }
}
