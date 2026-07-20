<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Expression;

class ExpressionEvaluator
{
    public function __construct(private VariableContext $context) {}

    public function evaluate(Expression $expression): mixed
    {
        // To be implemented: Ast visitor mapped to context
        return null;
    }
}
