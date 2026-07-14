<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto;

final readonly class Info
{
    public function __construct(
        public string $title,
        public ?string $summary,
        public ?string $description,
        public string $version,
    ) {
    }
}
