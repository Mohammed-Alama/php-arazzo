<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto;

final readonly class PayloadReplacement
{
    public function __construct(
        public string $target,
        public mixed $value,
    ) {
    }
}
