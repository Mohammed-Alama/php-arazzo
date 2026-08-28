<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts;

use Alama\Arazzo\Evaluation\EvaluationContext;
use Alama\Arazzo\Expression\Expression;

interface ExpressionEvaluatorInterface
{
    public function evaluate(Expression $expression, EvaluationContext $context): mixed;
}
