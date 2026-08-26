<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Condition\Ast;

use Alama\Arazzo\Evaluation\Condition\ComparisonOperator;

final readonly class Comparison implements ConditionNode
{
    public function __construct(
        public ComparisonOperator $op,
        public ConditionNode $left,
        public ConditionNode $right,
    ) {}
}
