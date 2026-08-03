<?php

declare(strict_types=1);

namespace Alama\Arazzo\Dto;

final readonly class Reusable
{
    public function __construct(
        public string $reference,
        public mixed $value = null,
    ) {
    }
}
