<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Condition;

use Alama\Arazzo\Evaluation\Enum\TokenKind;

final readonly class Token
{
    public function __construct(
        public TokenKind $kind,
        public string $value,
        public int $offset,
    ) {}
}
