<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel;

use Alama\Arazzo\Contracts\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Interfaces\OutputExtractorInterface;
use Alama\Arazzo\Contracts\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerRegistryInterface;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Laravel\Support\ConfigValue;
use Alama\Arazzo\Protocol\Http\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Protocol\Http\Execution\ResponseSchemaValidator;
use Alama\Arazzo\Protocol\Http\Execution\StepOutputExtractor;
use Alama\Arazzo\Protocol\Http\Registrar\ProtocolHttpRegistrar;
use Alama\Arazzo\Runner\OperationExecutorRegistry;
use Alama\Arazzo\Runner\ResponseValidatorDispatcher;
use Alama\Arazzo\Sources\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Sources\Resolver\SourceNormalizerRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Wires the protocol-http vertical slice into the composed app: value-seam
 * singletons (owned here since F1.3 — ExecutionBindings no longer builds
 * them), the D/E registries, and the `arazzo.plugins.*` tag vocabulary.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ProtocolHttpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/arazzo.php', 'arazzo');

        // These are *value* seams consumed by the graphs, not registry
        // entries, so they bind directly to their protocol-http
        // implementations.
        $this->app->singleton(DefaultOpenApiExecutor::class, function (Container $app): DefaultOpenApiExecutor {
            return new DefaultOpenApiExecutor(
                $app->make(ClientInterface::class),
                $app->make(RequestFactoryInterface::class),
                $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null,
            );
        });
        $this->app->singleton(
            OpenApiExecutorInterface::class,
            static fn (Container $app): OpenApiExecutorInterface => $app->make(DefaultOpenApiExecutor::class),
        );

        $this->app->singleton(StepOutputExtractor::class, function (Container $app): StepOutputExtractor {
            return new StepOutputExtractor(
                $app->make(OpenApiOperationResolver::class),
                $app->make(EvaluationEngineInterface::class),
                $app->make(ExpressionEngineInterface::class),
            );
        });
        $this->app->singleton(
            OutputExtractorInterface::class,
            static fn (Container $app): OutputExtractorInterface => $app->make(StepOutputExtractor::class),
        );

        $this->app->singleton(
            ResponseSchemaValidator::class,
            static fn (Container $app): ResponseSchemaValidator => new ResponseSchemaValidator(
                $app->make(OpenApiOperationResolver::class),
            ),
        );
        $this->app->singleton(
            ResponseValidatorInterface::class,
            static fn (Container $app): ResponseValidatorInterface => $app->make(ResponseSchemaValidator::class),
        );

        // The D/E registries, filled lazily through the registrar on first
        // resolve. F2.0 made the async worker consume the executor registry,
        // and boot()-time fill would freeze whatever instances were bound at
        // app boot — before tests re-point the seams. First resolve happens
        // at graph composition instead, so seam re-points are honoured.
        $this->app->singleton(
            SourceNormalizerRegistryInterface::class,
            static function (Container $app): SourceNormalizerRegistry {
                $registry = new SourceNormalizerRegistry();
                $app->make(ProtocolHttpRegistrar::class)->registerInto($registry);

                return $registry;
            },
        );
        $this->app->singleton(
            OperationExecutorRegistry::class,
            static function (Container $app): OperationExecutorRegistry {
                $registry = new OperationExecutorRegistry();
                foreach ($app->make(ProtocolHttpRegistrar::class)->executors() as $plugin) {
                    $registry->register($plugin);
                }

                return $registry;
            },
        );
        // ResponseValidatorDispatcher is immutable on main (ctor list, no
        // register()), so the container callback builds it from the
        // registrar's validator list instead of mutating it in boot().
        $this->app->singleton(
            ResponseValidatorDispatcher::class,
            static fn (Container $app): ResponseValidatorDispatcher => new ResponseValidatorDispatcher(
                $app->make(ProtocolHttpRegistrar::class)->validators(),
            ),
        );

        $this->app->singleton(ProtocolHttpRegistrar::class, function (Container $app): ProtocolHttpRegistrar {
            return new ProtocolHttpRegistrar(
                loader: $app->make(OpenApiDocumentLoader::class),
                operationResolver: $app->make(OpenApiOperationResolver::class),
                openApiExecutor: $app->make(OpenApiExecutorInterface::class),
                outputExtractor: $app->make(OutputExtractorInterface::class),
                schemaValidator: $app->make(ResponseValidatorInterface::class),
                evaluationEngine: $app->make(EvaluationEngineInterface::class),
                inspector: $app->make(ExpressionEngineInterface::class),
                pendingCorrelations: $app->make(PendingCorrelationRegistryInterface::class),
                httpClient: $app->make(HttpClientInterface::class),
                requestFactory: $app->bound(RequestFactoryInterface::class)
                    ? $app->make(RequestFactoryInterface::class)
                    : null,
                strictValidationDefault: ConfigValue::bool(config('arazzo.strict_schema_validation', false), false),
                idempotencyEnabled: ConfigValue::bool(config('arazzo.idempotency.enabled', false), false),
                idempotencyHeader: ConfigValue::string(config('arazzo.idempotency.header', 'Idempotency-Key'), 'Idempotency-Key'),
            );
        });

        // Tag vocabulary (G1/G3): ProtocolDiscovery::tags() lands with G;
        // until then the registrar owns the names and the provider registers
        // under the config defaults — the host never names the concretes.
        $tagVocabulary = ProtocolHttpRegistrar::tags();
        $this->app->tag(
            $tagVocabulary['arazzo.plugins.*'],
            ConfigValue::string(config('arazzo.plugins.tags.0'), 'arazzo.plugins.*'),
        );

        // The operation-executor sub-tag is what OperationExecutorRegistry
        // consumers read (G3).
        $this->app->tag(
            $tagVocabulary['arazzo.plugins.operation-executor'],
            ConfigValue::string(config('arazzo.plugins.operation_executor'), 'arazzo.plugins.operation-executor'),
        );
    }
}
