<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Dto;

class OpenApiPayload
{
    /**
     * @param array<string, mixed> $path
     * @param array<string, mixed> $query
     * @param array<string, mixed> $header
     * @param array<string, mixed> $auto
     */
    public function __construct(
        public array $path = [],
        public array $query = [],
        public array $header = [],
        public array $auto = [],
        public mixed $body = null,
    ) {
    }
}
