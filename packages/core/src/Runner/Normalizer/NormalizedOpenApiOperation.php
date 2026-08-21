<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Normalizer;

class NormalizedOpenApiOperation
{
    /**
     * @param array<int, array<string, mixed>> $parameters
     * @param array<string, mixed> $requestBodies
     * @param array<string, mixed> $responses
     */
    public function __construct(
        public readonly string $method,
        public readonly ?string $resolvedServerUrl,
        public readonly array $parameters,
        public readonly array $requestBodies,
        public readonly array $responses,
    ) {
    }
}
