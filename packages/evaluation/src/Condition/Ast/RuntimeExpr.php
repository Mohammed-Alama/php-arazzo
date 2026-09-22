<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Condition\Ast;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Evaluation\Interfaces\ConditionNode;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class RuntimeExpr implements ConditionNode
{
    public function __construct(
        public Expression $expression,
        public string $raw,
    ) {}
}
