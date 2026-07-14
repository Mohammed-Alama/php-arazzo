<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression;

final readonly class Token
{
    public function __construct(
        public TokenKind $kind,
        public string $value,
        public int $offset,
    ) {}
}
