<?php

declare(strict_types=1);

use Alama\Arazzo\Telemetry\OtelSetup;
use Alama\Arazzo\Telemetry\TraceContextPropagator;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;

it('exports spans with execution attributes through the configured exporter', function (): void {
    OtelSetup::reset();

    $exporter = new InMemoryExporter();
    OtelSetup::initialize(
        serviceName: 'arazzo-test',
        exporterOverride: $exporter,
    );

    $tracer = OtelSetup::getTracer();
    $span = $tracer->spanBuilder('arazzo.step.execute')
        ->setAttribute('execution.id', 'exec_otel_1')
        ->setAttribute('step.id', 's1')
        ->startSpan();
    $span->setStatus(StatusCode_OK());
    $span->end();

    OtelSetup::shutdown();

    $spans = iterator_to_array($exporter->getStorage());

    expect(count($spans))->toBeGreaterThanOrEqual(1)
        ->and($spans[0]->getName())->toBe('arazzo.step.execute')
        ->and($spans[0]->getAttributes()->get('execution.id'))->toBe('exec_otel_1')
        ->and($spans[0]->getAttributes()->get('step.id'))->toBe('s1');

    OtelSetup::reset();
});

it('trace context propagates into plain array carriers for queue boundaries', function (): void {
    OtelSetup::reset();
    OtelSetup::initialize(serviceName: 'arazzo-test', exporterOverride: new InMemoryExporter());

    $tracer = OtelSetup::getTracer();
    $parent = $tracer->spanBuilder('parent')->startSpan();
    $scope = $parent->activate();

    $propagator = new TraceContextPropagator();
    $carrier = [];
    $propagator->inject($carrier);

    $scope->detach();
    $parent->end();

    expect($carrier)->toHaveKey('traceparent')
        ->and($carrier['traceparent'])->toMatch('/^[0-9a-f]{2}-[0-9a-f]{32}-[0-9a-f]{16}-[0-9a-f]{2}$/');

    // Extract on the "worker side" yields a valid remote parent context.
    $extracted = $propagator->extract($carrier);
    expect($extracted)->not->toBeNull();

    OtelSetup::reset();
});

function StatusCode_OK(): string
{
    return StatusCode::STATUS_OK;
}
