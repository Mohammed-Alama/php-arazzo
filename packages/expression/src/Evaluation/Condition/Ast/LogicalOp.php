<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Evaluation\Condition\Ast;

use Alama\Arazzo\Expression\Evaluation\Enum\LogicalOperator;
use Alama\Arazzo\Expression\Evaluation\Interfaces\ConditionNode;

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
