<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Telemetry;

use ErrorException;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator as ApiTraceContextPropagator;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter as OtlpSpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\Stream\StreamTransportFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporter;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;

/**
 * Bootstraps OpenTelemetry tracing for the Arazzo runner.
 *
 * Exporters:
 *  - 'console'  JSON spans to STDOUT
 *  - 'file'     JSON-lines spans to a file (CLI runs, no collector needed)
 *  - 'otlp'     OTLP/HTTP against OTEL_EXPORTER_OTLP_ENDPOINT (default localhost:4318)
 *  - 'memory'   In-memory buffer (tests can read the spans back)
 *  - 'none'     no-op providers; zero overhead
 *
 * Environment knobs:
 *  - ARAZZO_OTEL_EXPORTER       one of the names above (default 'none')
 *  - ARAZZO_OTEL_FILE           target path for the 'file' exporter
 *  - OTEL_EXPORTER_OTLP_ENDPOINT base endpoint for the 'otlp' exporter
 */
final class OtelSetup
{
    public const EXPORTER_OTLP = 'otlp';

    public const EXPORTER_CONSOLE = 'console';

    public const EXPORTER_FILE = 'file';

    public const EXPORTER_MEMORY = 'memory';

    public const EXPORTER_NONE = 'none';

    private static bool $initialized = false;

    /** @internal injectable in-memory exporter so tests can assert on exported spans */
    public static ?InMemoryExporter $testExporter = null;

    /**
     * @param  array<string, string|int|float|bool>  $attributes
     */
    public static function initialize(
        ?string $exporter = null,
        string $serviceName = 'arazzo-runner',
        array $attributes = [],
        ?SamplerInterface $sampler = null,
        ?InMemoryExporter $exporterOverride = null,
    ): void {
        if (self::$initialized) {
            return;
        }

        if ($exporterOverride !== null) {
            self::$testExporter = $exporterOverride;
            $exporter = self::EXPORTER_MEMORY;
        } else {
            $exporter ??= self::envString('ARAZZO_OTEL_EXPORTER', self::EXPORTER_NONE);
        }

        $spanExporter = match ($exporter) {
            self::EXPORTER_OTLP => self::otlpExporter(),
            self::EXPORTER_CONSOLE => self::streamExporter(STDOUT),
            self::EXPORTER_FILE => self::fileExporter(),
            self::EXPORTER_MEMORY => self::$testExporter ?? new InMemoryExporter(),
            default => null,
        };

        $resource = ResourceInfoFactory::defaultResource()->merge(
            ResourceInfo::create(Attributes::create(array_merge(
                ['service.name' => $serviceName],
                $attributes,
            ))),
        );

        $builder = (new TracerProviderBuilder())
            ->setResource($resource)
            ->setSampler($sampler ?? new ParentBased(new AlwaysOnSampler()));

        if ($spanExporter !== null) {
            $builder = $builder->addSpanProcessor(new SimpleSpanProcessor($spanExporter));
        }

        $tracerProvider = $builder->build();

        Globals::registerInitializer(static fn (Configurator $configurator): Configurator => $configurator
            ->withTracerProvider($tracerProvider)
            ->withPropagator(self::propagator()));

        self::$initialized = true;
    }

    public static function isInitialized(): bool
    {
        return self::$initialized;
    }

    /** @internal resets bootstrapped state between tests */
    public static function reset(): void
    {
        Globals::reset();
        self::$initialized = false;
        self::$testExporter = null;
    }

    public static function getTracer(string $name = 'alama.arazzo.runner'): TracerInterface
    {
        return Globals::tracerProvider()->getTracer($name);
    }

    public static function propagator(): TextMapPropagatorInterface
    {
        return ApiTraceContextPropagator::getInstance();
    }

    /**
     * Flushes and shuts down the tracer provider so buffered spans reach the
     * exporter before process exit. Safe to call repeatedly.
     */
    public static function shutdown(): void
    {
        $provider = Globals::tracerProvider();

        if (method_exists($provider, 'shutdown')) {
            try {
                $provider->shutdown();
            } catch (\Throwable) {
                // best-effort flush at exit
            }
        }
    }

    private static function envString(string $key, string $fallback): string
    {
        $value = $_ENV[$key] ?? getenv($key);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    private static function otlpExporter(): SpanExporterInterface
    {
        $endpoint = self::envString('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://localhost:4318/v1/traces');

        return new OtlpSpanExporter((new OtlpHttpTransportFactory())->create($endpoint, 'application/x-protobuf'));
    }

    /**
     * @param  resource|string  $stream
     *
     * @throws ErrorException
     */
    private static function streamExporter($stream): SpanExporterInterface
    {
        return new ConsoleSpanExporter(new StreamTransportFactory()->create($stream, 'application/json'));
    }

    private static function fileExporter(): SpanExporterInterface
    {
        $path = self::envString('ARAZZO_OTEL_FILE', 'storage/arazzo-traces.jsonl');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $handle = fopen($path, 'ab');
        if ($handle === false) {
            throw new \RuntimeException("Could not open OTel trace file: {$path}");
        }

        return self::streamExporter($handle);
    }

    /** @internal exposed for tests that assert on exported spans */
    public static function memoryExporter(): InMemoryExporter
    {
        return new InMemoryExporter();
    }
}
