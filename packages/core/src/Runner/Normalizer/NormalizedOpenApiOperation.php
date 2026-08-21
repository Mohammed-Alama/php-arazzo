<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Normalizer;

class NormalizedOpenApiOperation
{
    /**
     * @param array<string, array<string, mixed>> $pathParameters
     * @param array<string, array<string, mixed>> $queryParameters
     * @param array<string, array<string, mixed>> $headerParameters
     * @param array<string, array<string, mixed>> $cookieParameters
     * @param array<string, mixed> $requestBodies
     * @param array<string, mixed> $responses
     */
    public function __construct(
        public readonly string $path,
        public readonly string $method,
        public readonly ?string $resolvedServerUrl,
        public readonly array $pathParameters,
        public readonly array $queryParameters,
        public readonly array $headerParameters,
        public readonly array $cookieParameters,
        public readonly array $requestBodies,
        public readonly array $responses,
    ) {
    }
}
