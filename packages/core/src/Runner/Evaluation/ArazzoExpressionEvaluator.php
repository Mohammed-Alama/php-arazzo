<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation;

use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionEvaluatorInterface;
use Alama\Arazzo\Spec\Expression;

class ArazzoExpressionEvaluator implements ExpressionEvaluatorInterface
{
    public function __construct(private ExpressionEvaluator $evaluator)
    {
    }

    public function evaluate(Expression $expression, EvaluationContext $context): mixed
    {
        return $this->evaluator->evaluate($expression, $context);
    }
}
