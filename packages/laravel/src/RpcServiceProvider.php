<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel;

use Alama\Arazzo\Contracts\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerRegistryInterface;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Laravel\Support\ConfigValue;
use Alama\Arazzo\Laravel\Support\SchemeSourceFetcher;
use Alama\Arazzo\Protocol\Http\Registrar\ProtocolHttpRegistrar;
use Alama\Arazzo\Protocol\Rpc\Execution\RpcOperationExecutor;
use Alama\Arazzo\Protocol\Rpc\Execution\RpcRequestBuilder;
use Alama\Arazzo\Protocol\Rpc\Normalizer\ProtoSourceNormalizer;
use Alama\Arazzo\Protocol\Rpc\Protocol\ProtobufOperationResolver;
use Alama\Arazzo\Protocol\Rpc\Protocol\RpcMessageCodec;
use Alama\Arazzo\Protocol\Rpc\Registrar\RpcRegistrar;
use Alama\Arazzo\Protocol\Soap\Registrar\SoapRegistrar;
use Alama\Arazzo\Runner\OperationExecutorRegistry;
use Alama\Arazzo\Sources\Resolver\Interfaces\SourceFetcher;
use Alama\Arazzo\Sources\Resolver\SourceNormalizerRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Wires the RPC vertical slice into the composed app (plan F5.2): value-seam
 * singletons, the RPC contribution to the D/E registries, and the
 * `arazzo.plugins.*` tag vocabulary.
 *
 * The shared D/E registries are re-bound as the union of the HTTP, SOAP and
 * RPC registrars (each guarded by `bound()`), so the protocol slices coexist
 * regardless of registration order. RPC contributes no response validators,
 * so ResponseValidatorDispatcher is deliberately left to the HTTP/SOAP
 * binding. Register this provider after ProtocolHttpServiceProvider (the
 * package's `extra.laravel.providers` order does).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RpcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/arazzo.php', 'arazzo');

        $this->registerValueSeams();
        $this->registerSharedRegistries();
        $this->registerTags();
    }

    private function registerValueSeams(): void
    {
        $this->app->singleton(
            SourceFetcher::class,
            static fn (Container $app): SchemeSourceFetcher => SchemeSourceFetcher::fromContainer($app),
        );
        $this->app->singleton(
            ProtoSourceNormalizer::class,
            static fn (): ProtoSourceNormalizer => new ProtoSourceNormalizer(),
        );
        $this->app->singleton(
            ProtobufOperationResolver::class,
            static fn (Container $app): ProtobufOperationResolver => new ProtobufOperationResolver(
                $app->make(SourceFetcher::class),
                $app->make(ProtoSourceNormalizer::class),
            ),
        );

        $this->app->singleton(
            RpcRequestBuilder::class,
            static fn (Container $app): RpcRequestBuilder => new RpcRequestBuilder(
                requestFactory: $app->make(RequestFactoryInterface::class),
                streamFactory: $app->make(StreamFactoryInterface::class),
                evaluationEngine: $app->make(EvaluationEngineInterface::class),
                inspector: $app->make(ExpressionEngineInterface::class),
                codec: new RpcMessageCodec(),
            ),
        );

        $this->app->singleton(RpcRegistrar::class, static fn (Container $app): RpcRegistrar => new RpcRegistrar(
            normalizer: $app->make(ProtoSourceNormalizer::class),
            httpClient: $app->make(HttpClientInterface::class),
            requestBuilder: $app->make(RpcRequestBuilder::class),
            operationResolver: $app->make(ProtobufOperationResolver::class),
        ));

        $this->app->singleton(
            RpcOperationExecutor::class,
            static fn (Container $app): RpcOperationExecutor => $app->make(RpcRegistrar::class)->executors()[0],
        );
    }

    /**
     * Re-binds the shared D/E registries as the union of the HTTP, SOAP and
     * RPC slices so every protocol resolves through one registry. Bound lazily
     * on first resolve (mirroring the HTTP/SOAP providers) so seam re-points
     * are honoured.
     */
    private function registerSharedRegistries(): void
    {
        $this->app->singleton(
            SourceNormalizerRegistryInterface::class,
            static function (Container $app): SourceNormalizerRegistry {
                $registry = new SourceNormalizerRegistry();

                if ($app->bound(ProtocolHttpRegistrar::class)) {
                    $app->make(ProtocolHttpRegistrar::class)->registerInto($registry);
                }

                if ($app->bound(SoapRegistrar::class)) {
                    $app->make(SoapRegistrar::class)->registerInto($registry);
                }

                $app->make(RpcRegistrar::class)->registerInto($registry);

                return $registry;
            },
        );

        $this->app->singleton(
            OperationExecutorRegistry::class,
            static function (Container $app): OperationExecutorRegistry {
                $registry = new OperationExecutorRegistry();

                if ($app->bound(ProtocolHttpRegistrar::class)) {
                    foreach ($app->make(ProtocolHttpRegistrar::class)->executors() as $plugin) {
                        $registry->register($plugin);
                    }
                }

                if ($app->bound(SoapRegistrar::class)) {
                    foreach ($app->make(SoapRegistrar::class)->executors() as $plugin) {
                        $registry->register($plugin);
                    }
                }

                foreach ($app->make(RpcRegistrar::class)->executors() as $plugin) {
                    $registry->register($plugin);
                }

                return $registry;
            },
        );

        // RPC contributes no response validators, so ResponseValidatorDispatcher
        // keeps the HTTP/SOAP union; re-binding it here would drop those.
    }

    private function registerTags(): void
    {
        $vocabulary = RpcRegistrar::tags();

        $this->app->tag(
            $vocabulary['arazzo.plugins.*'],
            ConfigValue::string(config('arazzo.plugins.tags.0'), 'arazzo.plugins.*'),
        );

        $this->app->tag(
            $vocabulary['arazzo.plugins.rpc-operation-executor'],
            ConfigValue::string(config('arazzo.plugins.rpc_operation_executor'), 'arazzo.plugins.rpc-operation-executor'),
        );
    }
}
