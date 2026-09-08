<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Evaluation\Condition\Ast;

use Alama\Arazzo\Expression\Evaluation\Interfaces\ConditionNode;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class Literal implements ConditionNode
{
    public function __construct(
        public string|int|float|bool|null $value,
    ) {}
}
