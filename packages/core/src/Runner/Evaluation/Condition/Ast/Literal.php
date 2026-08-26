<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation\Condition\Ast;

final readonly class Literal implements ConditionNode
{
    public function __construct(
        public string|int|float|bool|null $value,
    ) {}
}
