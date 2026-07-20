<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Jobs;

final class ResumeCorrelationJob
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $correlationId,
        public array $payload,
    ) {
    }
}
