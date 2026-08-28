<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Condition\Ast;

use Alama\Arazzo\Expression\Expression;

final readonly class RuntimeExpr implements ConditionNode
{
    public function __construct(
        public Expression $expression,
        public string $raw,
    ) {}
}
