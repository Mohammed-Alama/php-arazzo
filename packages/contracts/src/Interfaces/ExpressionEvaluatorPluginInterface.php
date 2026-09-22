<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

use Alama\Arazzo\Contracts\Spec\Expression;

interface ExpressionEvaluatorPluginInterface extends PluginInterface
{
    public function supports(Expression $expression): bool;

    public function evaluate(Expression $expression, mixed $context): mixed;
}
