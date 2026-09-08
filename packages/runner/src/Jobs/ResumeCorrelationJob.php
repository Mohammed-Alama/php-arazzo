<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Jobs;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ResumeCorrelationJob
{
    /**
     * @param  array{statusCode?: int, headers?: array<string, mixed>, body?: mixed}  $response
     */
    public function __construct(
        public readonly string $correlationId,
        public readonly array $response,
    ) {}
}
