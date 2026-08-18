<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution;

use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Execution\Contracts\ExpressionEvaluatorInterface;

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
