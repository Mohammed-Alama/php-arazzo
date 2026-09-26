<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Condition\Ast;

use Alama\Arazzo\Evaluation\Enum\LogicalOperator;
use Alama\Arazzo\Evaluation\Interfaces\ConditionNode;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class LogicalOp implements ConditionNode
{
    public function __construct(
        public LogicalOperator $op,
        public ConditionNode $left,
        public ConditionNode $right,
    ) {}
}
