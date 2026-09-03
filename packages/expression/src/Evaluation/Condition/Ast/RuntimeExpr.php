<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Evaluation\Condition\Ast;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Expression\Evaluation\Interfaces\ConditionNode;

final readonly class RuntimeExpr implements ConditionNode
{
    public function __construct(
        public Expression $expression,
        public string $raw,
    ) {}
}
