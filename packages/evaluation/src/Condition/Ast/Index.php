<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Condition\Ast;

/**
 * Zero-based index accessor (`[n]`) on a simple-condition operand.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class Index implements Accessor
{
    public function __construct(public int $index) {}
}
