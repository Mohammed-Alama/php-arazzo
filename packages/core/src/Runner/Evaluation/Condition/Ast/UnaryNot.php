<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation\Condition\Ast;

final readonly class UnaryNot implements ConditionNode
{
    public function __construct(
        public ConditionNode $operand,
    ) {
    }
}
