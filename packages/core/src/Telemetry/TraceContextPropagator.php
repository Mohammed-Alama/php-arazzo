<?php

declare(strict_types=1);

namespace Alama\Arazzo\Telemetry;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator as ApiTraceContextPropagator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;

/**
 * Carries W3C trace context across process boundaries (queue jobs, CLI
 * resume) so child spans link into the parent run's trace.
 *
 * Carriers are plain string-keyed arrays — queue job payloads, PSR-7 header
 * arrays, or CLI env maps all work without framework coupling.
 */
final class TraceContextPropagator
{
    private const TRACEPARENT = 'traceparent';

    private const TRACESTATE = 'tracestate';

    public function __construct(private readonly ApiTraceContextPropagator $propagator = new ApiTraceContextPropagator()) {}

    /**
     * Writes traceparent/tracestate for the current (or given) context into
     * the carrier array.
     *
     * @param  array<string, mixed>  $carrier
     */
    public function inject(array &$carrier, ?ContextInterface $context = null): void
    {
        $spanContext = Span::fromContext($context ?? Context::getCurrent())->getContext();

        if (!$spanContext->isValid()) {
            return;
        }

        $carrier[self::TRACEPARENT] = sprintf(
            '%02x-%s-%s-%02x',
            0,
            $spanContext->getTraceId(),
            $spanContext->getSpanId(),
            $spanContext->getTraceFlags() & 1,
        );

        $tracestate = (string) $spanContext->getTraceState();
        if ($tracestate !== '') {
            $carrier[self::TRACESTATE] = $tracestate;
        }
    }

    /**
     * Reads traceparent/tracestate from the carrier and returns a context
     * with the remote parent applied.
     *
     * @param  array<string, mixed>  $carrier
     */
    public function extract(array $carrier): ContextInterface
    {
        return $this->propagator->extract($carrier);
    }

    /**
     * @param  array<string, mixed>  $carrier
     */
    public static function traceparentOf(array $carrier): ?string
    {
        foreach ([self::TRACEPARENT, 'Traceparent', 'HTTP_TRACEPARENT', 'REDIRECT_TRACEPARENT'] as $key) {
            if (isset($carrier[$key]) && is_string($carrier[$key])) {
                return $carrier[$key];
            }
        }

        return null;
    }
}
