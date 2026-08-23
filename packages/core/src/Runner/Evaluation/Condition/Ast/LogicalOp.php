<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation\Condition\Ast;

final readonly class LogicalOp implements ConditionNode
{
    /** @param 'and'|'or' $op */
    public function __construct(
        public string $op,
        public ConditionNode $left,
        public ConditionNode $right,
    ) {
    }
}
