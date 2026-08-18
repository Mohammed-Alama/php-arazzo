<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

final class ResumeCorrelationJob
{
    /**
     * @param array{statusCode?: int, headers?: array<string, mixed>, body?: mixed} $response
     */
    public function __construct(
        public readonly string $correlationId,
        public readonly array $response,
    ) {
    }
}
