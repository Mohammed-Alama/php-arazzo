<?php

declare(strict_types=1);

use Alama\Arazzo\Runner\Telemetry\OtelSetup;
use Alama\Arazzo\Runner\Telemetry\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;

beforeEach(function (): void {
    OtelSetup::reset();
    OtelSetup::initialize(serviceName: 'arazzo-test', exporterOverride: new InMemoryExporter());
});

afterEach(function (): void {
    OtelSetup::reset();
});

it('injects nothing into the carrier when there is no active span context', function (): void {
    $propagator = new TraceContextPropagator();
    $carrier = [];

    $propagator->inject($carrier);

    expect($carrier)->toBe([]);
});

it('injects a well-formed traceparent for the active span', function (): void {
    $tracer = OtelSetup::getTracer();
    $span = $tracer->spanBuilder('parent')->startSpan();
    $scope = $span->activate();

    $propagator = new TraceContextPropagator();
    $carrier = [];
    $propagator->inject($carrier);

    $scope->detach();
    $span->end();

    expect($carrier)->toHaveKey('traceparent')
        ->and($carrier['traceparent'])->toMatch('/^00-[0-9a-f]{32}-[0-9a-f]{16}-[0-9a-f]{2}$/');
});

it('omits tracestate from the carrier when the span has none', function (): void {
    $tracer = OtelSetup::getTracer();
    $span = $tracer->spanBuilder('parent')->startSpan();
    $scope = $span->activate();

    $propagator = new TraceContextPropagator();
    $carrier = [];
    $propagator->inject($carrier);

    $scope->detach();
    $span->end();

    expect($carrier)->toHaveKey('traceparent')
        ->and($carrier)->not->toHaveKey('tracestate');
});

it('extracts a valid remote parent context from a traceparent carrier', function (): void {
    $tracer = OtelSetup::getTracer();
    $span = $tracer->spanBuilder('parent')->startSpan();
    $scope = $span->activate();

    $propagator = new TraceContextPropagator();
    $carrier = [];
    $propagator->inject($carrier);

    $scope->detach();
    $span->end();

    $extracted = $propagator->extract($carrier);
    $spanContext = Span::fromContext($extracted)->getContext();

    expect($extracted)->toBeInstanceOf(ContextInterface::class)
        ->and($spanContext->isValid())->toBeTrue();
});

it('traceparentOf reads the lower-case traceparent key', function (): void {
    expect(TraceContextPropagator::traceparentOf(['traceparent' => '00-abc']))->toBe('00-abc');
});

it('traceparentOf reads the title-cased Traceparent header key', function (): void {
    expect(TraceContextPropagator::traceparentOf(['Traceparent' => '00-abc']))->toBe('00-abc');
});

it('traceparentOf reads the HTTP_ and REDIRECT_ prefixed keys', function (): void {
    expect(TraceContextPropagator::traceparentOf(['HTTP_TRACEPARENT' => 'a']))->toBe('a')
        ->and(TraceContextPropagator::traceparentOf(['REDIRECT_TRACEPARENT' => 'b']))->toBe('b');
});

it('traceparentOf returns null when no traceparent key is present', function (): void {
    expect(TraceContextPropagator::traceparentOf([]))->toBeNull()
        ->and(TraceContextPropagator::traceparentOf(['tracestate' => 'x']))->toBeNull();
});

it('traceparentOf ignores a non-string traceparent value', function (): void {
    expect(TraceContextPropagator::traceparentOf(['traceparent' => 1234]))->toBeNull();
});
