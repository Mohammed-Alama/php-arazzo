<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Bindings;

use Alama\Arazzo\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\Fetchers\CachedFetcher;
use Alama\Arazzo\Resolver\Fetchers\HttpFetcher;
use Alama\Arazzo\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Resolver\SourceRegistry;
use Alama\Arazzo\Resolver\SourceResolver;
use Alama\Arazzo\Runner\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Runner\Evaluation\SelectorEvaluator;
use Alama\Arazzo\Runner\Evaluation\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Runner\Evaluation\Xpath\XpathEvaluator;
use Alama\Arazzo\Runner\Execution\OpenApiDocumentLoader;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Validator\PreflightValidator;
use Illuminate\Contracts\Cache\Repository as CacheInterface;
use Illuminate\Contracts\Container\Container;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/** Source resolution + OpenAPI operation resolution + capability evaluators. */
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

        $app->singleton(XpathEvaluator::class, fn (Container $app) => new DomXpathEvaluator());

        $app->singleton(SelectorEvaluator::class, function (Container $app) {
            return new SelectorEvaluator(
                new DomXpathEvaluator(),
                new ExpressionEvaluator(),
            );
        });

        $app->singleton(PreflightValidator::class, function (Container $app) {
            return new PreflightValidator(
                $app->make(SourceRegistry::class),
                $app->make(OpenApiOperationResolver::class),
                new DomXpathEvaluator(),
            );
        });
    }
}
