<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation\Condition\Ast;

use Alama\Arazzo\Runner\Evaluation\Condition\LogicalOperator;

final readonly class LogicalOp implements ConditionNode
{
    public function __construct(
        public LogicalOperator $op,
        public ConditionNode $left,
        public ConditionNode $right,
    ) {
    }
}
