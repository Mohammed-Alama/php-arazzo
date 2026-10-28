<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression\Support\Abnf;

/**
 * @internal stays out of the advertised contract; test support only, never shipped.
 *
 * A case-insensitive quoted character string: "string".
 */
final class AbnfCharVal
{
    public function __construct(public string $value) {}
}
