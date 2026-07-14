<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto;

use Alama\LaravelArazzo\Dto\Enum\Format;

final readonly class RawDocument
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public array $data,
        public string $path,
        public Format $format,
    ) {}
}
