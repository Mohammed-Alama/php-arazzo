<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts;

use Alama\Arazzo\Runner\Evaluation\EvaluationContext;
use Alama\Arazzo\Spec\Expression;

interface ExpressionEvaluatorInterface
{
    public function evaluate(Expression $expression, EvaluationContext $context): mixed;
}
