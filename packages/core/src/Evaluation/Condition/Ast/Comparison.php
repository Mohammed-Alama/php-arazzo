<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Condition\Ast;

use Alama\Arazzo\Evaluation\Enum\ComparisonOperator;
use Alama\Arazzo\Evaluation\Interfaces\ConditionNode;

final readonly class Comparison implements ConditionNode
{
    public function __construct(
        public ComparisonOperator $op,
        public ConditionNode $left,
        public ConditionNode $right,
    ) {}
}
