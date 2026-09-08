<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Data;

use Alama\Arazzo\Expression\Enum\TokenKind;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class Token
{
    public function __construct(
        public TokenKind $kind,
        public string $value,
        public int $offset,
    ) {}
}
