<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression\Support\Abnf;

/**
 * @internal stays out of the advertised contract; test support only, never shipped.
 *
 * A numeric value: %x00-2E or %x30-7A etc., possibly dotted (concatenation).
 */
final class AbnfNumVal
{
    /** @param array{int|string}  $values */
    public function __construct(public array $values) {}
}
