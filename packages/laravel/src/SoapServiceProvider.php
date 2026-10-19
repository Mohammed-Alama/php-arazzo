<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel;

use Alama\Arazzo\Contracts\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Interfaces\ReplacementTargetResolverInterface;
use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerRegistryInterface;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Laravel\Support\ConfigValue;
use Alama\Arazzo\Laravel\Support\SchemeSourceFetcher;
use Alama\Arazzo\Protocol\Http\Registrar\ProtocolHttpRegistrar;
use Alama\Arazzo\Protocol\Soap\Execution\SoapOperationExecutor;
use Alama\Arazzo\Protocol\Soap\Execution\SoapRequestBuilder;
use Alama\Arazzo\Protocol\Soap\Execution\WsdlOperationResolver;
use Alama\Arazzo\Protocol\Soap\Registrar\SoapRegistrar;
use Alama\Arazzo\Protocol\Soap\Resolver\XPathReplacementTargetResolver;
use Alama\Arazzo\Protocol\Soap\Validation\XsdResponseValidator;
use Alama\Arazzo\Runner\OperationExecutorRegistry;
use Alama\Arazzo\Runner\ResponseValidatorDispatcher;
use Alama\Arazzo\Sources\Normalizer\WsdlSourceNormalizer;
use Alama\Arazzo\Sources\Resolver\Interfaces\SourceFetcher;
use Alama\Arazzo\Sources\Resolver\SourceNormalizerRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Wires the SOAP vertical slice into the composed app (plan F4.6): value-seam
 * singletons, the SOAP contribution to the D/E registries, and the
 * `arazzo.plugins.*` tag vocabulary.
 *
 * The shared D/E registries are re-bound as the union of the HTTP and SOAP
 * registrars when the HTTP provider is present, so the two protocol slices
 * coexist. Register this provider after ProtocolHttpServiceProvider (the
 * package's `extra.laravel.providers` order does).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class SoapServiceProvider extends ServiceProvider
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
            WsdlSourceNormalizer::class,
            static fn (): WsdlSourceNormalizer => new WsdlSourceNormalizer(),
        );
        $this->app->singleton(
            WsdlOperationResolver::class,
            static fn (Container $app): WsdlOperationResolver => new WsdlOperationResolver(
                $app->make(SourceFetcher::class),
                $app->make(WsdlSourceNormalizer::class),
            ),
        );

        $this->app->singleton(
            XPathReplacementTargetResolver::class,
            static fn (): XPathReplacementTargetResolver => new XPathReplacementTargetResolver(),
        );
        $this->app->singleton(
            ReplacementTargetResolverInterface::class,
            static fn (Container $app): ReplacementTargetResolverInterface => $app->make(XPathReplacementTargetResolver::class),
        );

        $this->app->singleton(
            SoapRequestBuilder::class,
            static fn (Container $app): SoapRequestBuilder => new SoapRequestBuilder(
                requestFactory: $app->make(RequestFactoryInterface::class),
                streamFactory: $app->make(StreamFactoryInterface::class),
                evaluationEngine: $app->make(EvaluationEngineInterface::class),
                replacementResolver: $app->make(ReplacementTargetResolverInterface::class),
            ),
        );

        $this->app->singleton(SoapRegistrar::class, static fn (Container $app): SoapRegistrar => new SoapRegistrar(
            normalizer: $app->make(WsdlSourceNormalizer::class),
            httpClient: $app->make(HttpClientInterface::class),
            requestBuilder: $app->make(SoapRequestBuilder::class),
            operationResolver: $app->make(WsdlOperationResolver::class),
        ));

        $this->app->singleton(
            SoapOperationExecutor::class,
            static fn (Container $app): SoapOperationExecutor => $app->make(SoapRegistrar::class)->executors()[0],
        );
        $this->app->singleton(
            XsdResponseValidator::class,
            static fn (Container $app): XsdResponseValidator => $app->make(SoapRegistrar::class)->validators()[0],
        );
    }

    /**
     * Re-binds the shared D/E registries as the union of the HTTP and SOAP
     * slices so both protocols resolve through one registry. Bound lazily on
     * first resolve (mirroring the HTTP provider) so seam re-points are
     * honoured.
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

                $app->make(SoapRegistrar::class)->registerInto($registry);

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

                foreach ($app->make(SoapRegistrar::class)->executors() as $plugin) {
                    $registry->register($plugin);
                }

                return $registry;
            },
        );

        // ResponseValidatorDispatcher is immutable (ctor list), so the
        // container callback builds it from the combined validator lists.
        $this->app->singleton(
            ResponseValidatorDispatcher::class,
            static function (Container $app): ResponseValidatorDispatcher {
                $validators = $app->bound(ProtocolHttpRegistrar::class)
                    ? $app->make(ProtocolHttpRegistrar::class)->validators()
                    : [];

                return new ResponseValidatorDispatcher([
                    ...$validators,
                    ...$app->make(SoapRegistrar::class)->validators(),
                ]);
            },
        );
    }

    private function registerTags(): void
    {
        $vocabulary = SoapRegistrar::tags();

        $this->app->tag(
            $vocabulary['arazzo.plugins.*'],
            ConfigValue::string(config('arazzo.plugins.tags.0'), 'arazzo.plugins.*'),
        );

        $this->app->tag(
            $vocabulary['arazzo.plugins.soap-operation-executor'],
            ConfigValue::string(config('arazzo.plugins.soap_operation_executor'), 'arazzo.plugins.soap-operation-executor'),
        );
    }
}
