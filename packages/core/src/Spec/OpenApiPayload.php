<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec;

final readonly class OpenApiPayload
{
    /**
     * @param  array<string, mixed>  $path
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $cookie
     * @param  array<string, mixed>  $auto
     */
    public function __construct(
        public array $path = [],
        public array $query = [],
        public array $header = [],
        public array $cookie = [],
        public array $auto = [],
        public mixed $body = null,
        public ?string $bodyMediaType = null,
    ) {}
}
