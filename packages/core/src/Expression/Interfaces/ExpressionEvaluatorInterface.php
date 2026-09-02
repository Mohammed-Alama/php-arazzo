<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Interfaces;

use Alama\Arazzo\Evaluation\Data\EvaluationContext;
use Alama\Arazzo\Spec\Expression;

interface ExpressionEvaluatorInterface
{
    public function evaluate(Expression $expression, EvaluationContext $context): mixed;
}
