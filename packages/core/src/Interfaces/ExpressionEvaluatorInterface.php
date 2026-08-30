<?php

declare(strict_types=1);

namespace Alama\Arazzo\Interfaces;

use Alama\Arazzo\Spec\EvaluationContext;
use Alama\Arazzo\Spec\Expression;

interface ExpressionEvaluatorInterface
{
    public function evaluate(Expression $expression, EvaluationContext $context): mixed;
}
