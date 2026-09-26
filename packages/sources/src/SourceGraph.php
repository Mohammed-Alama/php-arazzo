<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources;

use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Document\ModelStack;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Sources\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Sources\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Sources\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Sources\Resolver\Fetchers\HttpFetcher;
use Alama\Arazzo\Sources\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Sources\Resolver\SourceRegistry;
use Alama\Arazzo\Sources\Validator\PreflightValidator;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Composition root for source resolution. This is the only class in the
 * repository permitted to construct a Guzzle client.
 *
 * It is also the sources package's entry-point facade, which is why
 * building the expression engine here is allowed: facade-to-facade
 * construction is the seam policy's permitted exception. ModelStack, which
 * is not a facade, receives the engine instead of constructing it.
 */
final class SourceGraph
{
    public static function default(): DocumentInterface
    {
        return self::using();
    }

    public static function using(
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $httpFactory = null,
        ?SourceRegistry $registry = null,
    ): DocumentInterface {
        $client = $httpClient ?? new Client();
        $factory = $httpFactory ?? new HttpFactory();

        $sources = $registry ?? new SourceRegistry(new DefaultSourceResolver([
            'http' => new HttpFetcher($client, $factory),
            'https' => new HttpFetcher($client, $factory),
            'file' => new LocalFetcher(),
        ]));

        $versionDetector = new OpenApiVersionDetector();

        $operations = new OpenApiOperationResolver(
            new OpenApiDocumentLoader($sources),
            $versionDetector,
            new OpenApi30Normalizer(),
            new OpenApi31Normalizer(),
        );

        return new Document(
            model: ModelStack::default(new ExpressionEngine()),
            sources: $sources,
            operations: $operations,
            versionDetector: $versionDetector,
            preflight: new PreflightValidator($sources, $operations),
        );
    }
}
