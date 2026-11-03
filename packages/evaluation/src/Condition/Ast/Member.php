<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Condition\Ast;

/**
 * Property de-reference accessor (`.name`) on a simple-condition operand.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class Member implements Accessor
{
    public function __construct(public string $name) {}
}
