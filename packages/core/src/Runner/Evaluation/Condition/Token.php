<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation\Condition;

final readonly class Token
{
    /**
     * @param 'and'|'or'|'eq'|'neq'|'gt'|'gte'|'lt'|'lte'|'not'|'lparen'|'rparen'|'number'|'string'|'ident'|'expr' $kind
     */
    public function __construct(
        public string $kind,
        public string $value,
        public int $offset,
    ) {
    }
}
