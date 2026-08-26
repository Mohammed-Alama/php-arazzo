<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Condition\Ast;

use Alama\Arazzo\Evaluation\Condition\LogicalOperator;

final readonly class LogicalOp implements ConditionNode
{
    public function __construct(
        public LogicalOperator $op,
        public ConditionNode $left,
        public ConditionNode $right,
    ) {}
}
