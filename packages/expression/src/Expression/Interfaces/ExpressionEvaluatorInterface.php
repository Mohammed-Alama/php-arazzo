<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Interfaces;

use Alama\Arazzo\Spec\Expression;

interface ExpressionEvaluatorInterface
{
    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed;
}
