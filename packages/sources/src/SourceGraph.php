<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources;

use Alama\Arazzo\Document\Document as DocumentFacade;
use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Document\ModelStack;
use Alama\Arazzo\Document\Parser\Decoders\NativeJsonDecoder;
use Alama\Arazzo\Document\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Document\Parser\Loader;
use Alama\Arazzo\Document\Parser\Parser;
use Alama\Arazzo\Document\Validator\RuleSet;
use Alama\Arazzo\Document\Validator\Validator;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Sources\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Sources\Normalizer\OpenApiSourceNormalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Sources\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Sources\Resolver\Fetchers\HttpFetcher;
use Alama\Arazzo\Sources\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Sources\Resolver\SourceNormalizerRegistry;
use Alama\Arazzo\Sources\Resolver\SourceRegistry;
use Alama\Arazzo\Sources\Validator\PreflightValidator;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Composition root for the *sources* package.
 * It is the only place that constructs Guzzle / PSR‑HTTP objects.
 */
final class SourceGraph
{
    private static ?SourceLoader $cachedLoader = null;

    /**
     * Create a source registry using the given HTTP client and request factory.
     * This hides the fetcher implementation details from consumers.
     */
    public static function createRegistry(
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $httpFactory = null,
    ): SourceRegistry {
        $client = $httpClient ?? new Client();
        $factory = $httpFactory ?? new HttpFactory();

        return new SourceRegistry(DefaultSourceResolver::withFetchers([
            'http' => new HttpFetcher($client, $factory),
            'https' => new HttpFetcher($client, $factory),
            'file' => new LocalFetcher(),
        ]));
    }

    /**
     * Generic façade only – pure document pipeline.
     */
    public static function document(?OpenApiOperationResolver $operationResolver = null): DocumentInterface
    {
        $operations = $operationResolver ?? self::operations(new SourceRegistry(DefaultSourceResolver::withDefaults()));

        return new DocumentFacade(
            new ModelStack(
                loader: new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder()),
                parser: new Parser(),
                validator: new Validator(RuleSet::default(new ExpressionEngine())),
                engine: new ExpressionEngine(),
                preflight: new PreflightValidator(
                    new SourceRegistry(DefaultSourceResolver::withDefaults()),
                    $operations,
                ),
                operationResolver: $operationResolver,
            ),
            $operationResolver,
        );
    }

    /** Source‑only loader (no generic pipeline) - cached singleton */
    public static function loader(?SourceRegistry $registry = null): SourceLoader
    {
        // Only use cache when no custom registry is provided
        if ($registry === null && self::$cachedLoader !== null) {
            return self::$cachedLoader;
        }

        $defaultRegistry = $registry ?? new SourceRegistry(DefaultSourceResolver::withDefaults());
        $defaultOperations = self::operations($defaultRegistry);

        // Build normalizer registry
        $normalizerRegistry = new SourceNormalizerRegistry();
        $normalizerRegistry->register(new OpenApiSourceNormalizer(
            new OpenApiDocumentLoader($defaultRegistry),
            new OpenApiVersionDetector(),
            new OpenApi30Normalizer(),
            new OpenApi31Normalizer(),
        ));

        $loader = new SourceLoader(
            sources: $defaultRegistry,
            operations: $defaultOperations,
            versionDetector: new OpenApiVersionDetector(),
            preflight: new PreflightValidator(
                new SourceRegistry(DefaultSourceResolver::withDefaults()),
                $defaultOperations,
            ),
            normalizers: $normalizerRegistry,
        );

        // Only cache when using default registry
        if ($registry === null) {
            self::$cachedLoader = $loader;
        }

        return $loader;
    }

    /** Bundle both independent façades for the runner */
    public static function runtime(?SourceRegistry $registry = null): SourceRuntime
    {
        $sources = $registry ?? new SourceRegistry(DefaultSourceResolver::withDefaults());

        $operations = self::operations($sources);

        $normalizerRegistry = new SourceNormalizerRegistry();
        $normalizerRegistry->register(new OpenApiSourceNormalizer(
            new OpenApiDocumentLoader($sources),
            new OpenApiVersionDetector(),
            new OpenApi30Normalizer(),
            new OpenApi31Normalizer(),
        ));

        $loader = new SourceLoader(
            sources: $sources,
            operations: $operations,
            versionDetector: new OpenApiVersionDetector(),
            preflight: new PreflightValidator($sources, $operations),
            normalizers: $normalizerRegistry,
        );
        $document = self::document($operations);

        return new SourceRuntime(
            document: $document,
            loader: $loader,
            operations: $operations,
        );
    }

    /** Generic façade only – pure document pipeline */
    public static function using(?SourceRegistry $registry = null): DocumentInterface
    {
        return self::document();
    }

    /** Factory used by the two public methods above */
    private static function operations(SourceRegistry $sources): OpenApiOperationResolver
    {
        $detector = new OpenApiVersionDetector();

        return new OpenApiOperationResolver(
            new OpenApiDocumentLoader($sources),
            $detector,
            new OpenApi30Normalizer(),
            new OpenApi31Normalizer(),
        );
    }
}
