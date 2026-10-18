<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Async;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ResumeCorrelationJob
{
    /**
     * The response is the resumption payload as it arrived -- a webhook body,
     * for instance -- rather than an HTTP envelope. CorrelationResumer reads
     * statusCode and body defensively and keeps the rest verbatim, so this is
     * a name-to-value map rather than a shaped array.
     *
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        public readonly string $correlationId,
        public readonly array $response,
    ) {}
}
