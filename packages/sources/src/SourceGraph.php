<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources;

use Alama\Arazzo\Document\Document as DocumentFacade;
use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Document\ModelStack;
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
     * The default source registry for this slice.
     *
     * Deliberately NOT memoised. A resolver memoises nothing itself, but the
     * loader behind it caches parsed documents keyed by source name, so a
     * process-wide shared resolver would serve the first caller's document to
     * every later caller that reuses a source name. Callers that want one
     * instance across several entry points inject it (see `runtime()`).
     */
    private static function defaultRegistry(): SourceRegistry
    {
        return new SourceRegistry(DefaultSourceResolver::withDefaults());
    }

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

    public static function document(?OpenApiOperationResolver $operationResolver = null): DocumentInterface
    {
        $operations = $operationResolver ?? self::operations();

        return new DocumentFacade(
            new ModelStack(
                loader: Loader::new(),
                parser: new Parser(),
                validator: new Validator(RuleSet::default(new ExpressionEngine())),
                engine: new ExpressionEngine(),
                preflight: new PreflightValidator(
                    self::defaultRegistry(),
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

        $defaultRegistry = $registry ?? self::defaultRegistry();
        $defaultOperations = self::operations($registry);

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
            versionDetector: new OpenApiVersionDetector(),
            preflight: new PreflightValidator(
                $defaultRegistry,
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

    /**
     * Bundle both independent façades for the runner
     *
     * Pass `$operations` when the caller already holds a resolver, so the
     * document and the resolver share one transport instead of each building
     * a loader (and its document cache) of their own.
     *
     * @return SourceRuntime<OpenApiOperationResolver>
     */
    public static function runtime(
        ?SourceRegistry $registry = null,
        ?OpenApiOperationResolver $operations = null,
    ): SourceRuntime {
        $sources = $registry ?? self::defaultRegistry();

        $operations ??= self::buildOperations($sources);

        $normalizerRegistry = new SourceNormalizerRegistry();
        $normalizerRegistry->register(new OpenApiSourceNormalizer(
            new OpenApiDocumentLoader($sources),
            new OpenApiVersionDetector(),
            new OpenApi30Normalizer(),
            new OpenApi31Normalizer(),
        ));

        $loader = new SourceLoader(
            sources: $sources,
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

    /**
     * Build the concrete operation resolver for this slice.
     *
     * Public so a container can bind the resolver once and hand that same
     * instance to `runtime()`, instead of letting the graph build a second one
     * behind its back.
     */
    public static function operations(?SourceRegistry $registry = null): OpenApiOperationResolver
    {
        return self::buildOperations($registry ?? self::defaultRegistry());
    }

    private static function buildOperations(SourceRegistry $sources): OpenApiOperationResolver
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
