<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Bindings;

use Alama\Arazzo\Sources\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Sources\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Sources\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Sources\Resolver\Fetchers\CachedFetcher;
use Alama\Arazzo\Sources\Resolver\Fetchers\HttpFetcher;
use Alama\Arazzo\Sources\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Sources\Resolver\Interfaces\SourceResolver;
use Alama\Arazzo\Sources\Resolver\SourceRegistry;
use Alama\Arazzo\Sources\Validator\PreflightValidator;
use Illuminate\Contracts\Cache\Repository as CacheInterface;
use Illuminate\Contracts\Container\Container;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/** Source resolution + OpenAPI operation resolution + capability evaluators.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ResolverBindings
{
    public static function register(Container $app): void
    {
        $app->singleton(SourceResolver::class, function (Container $app) {
            $defaultResolver = new DefaultSourceResolver(
                fetchers: [
                    'http' => new CachedFetcher(new HttpFetcher($app->make(ClientInterface::class), $app->make(RequestFactoryInterface::class)), $app->make(CacheInterface::class), 3600),
                    'https' => new CachedFetcher(new HttpFetcher($app->make(ClientInterface::class), $app->make(RequestFactoryInterface::class)), $app->make(CacheInterface::class), 3600),
                    'file' => new LocalFetcher(),
                ],
            );

            return new SourceRegistry($defaultResolver);
        });

        $app->singleton(SourceRegistry::class, fn (Container $app) => $app->make(SourceResolver::class));

        $app->singleton(OpenApiDocumentLoader::class, function (Container $app) {
            return new OpenApiDocumentLoader($app->make(SourceRegistry::class));
        });

        $app->singleton(OpenApiOperationResolver::class, function (Container $app) {
            return new OpenApiOperationResolver(
                $app->make(OpenApiDocumentLoader::class),
                $app->make(OpenApiVersionDetector::class),
                new OpenApi30Normalizer(),
                new OpenApi31Normalizer(),
            );
        });

        $app->singleton(PreflightValidator::class, function (Container $app) {
            return new PreflightValidator(
                $app->make(SourceRegistry::class),
                $app->make(OpenApiOperationResolver::class),
            );
        });
    }
}
