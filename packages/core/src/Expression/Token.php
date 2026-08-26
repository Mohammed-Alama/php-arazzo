<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

final readonly class Token
{
    public function __construct(
        public TokenKind $kind,
        public string $value,
        public int $offset,
    ) {}
}
