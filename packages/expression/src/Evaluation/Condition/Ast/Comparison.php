<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Evaluation\Condition\Ast;

use Alama\Arazzo\Expression\Evaluation\Enum\ComparisonOperator;
use Alama\Arazzo\Expression\Evaluation\Interfaces\ConditionNode;

final readonly class Comparison implements ConditionNode
{
    public function __construct(
        public ComparisonOperator $op,
        public ConditionNode $left,
        public ConditionNode $right,
    ) {}
}
