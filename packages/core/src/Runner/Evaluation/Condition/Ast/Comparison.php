<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation\Condition\Ast;

final readonly class Comparison implements ConditionNode
{
    /** @param 'eq'|'neq'|'gt'|'gte'|'lt'|'lte' $op */
    public function __construct(
        public string $op,
        public ConditionNode $left,
        public ConditionNode $right,
    ) {
    }
}
