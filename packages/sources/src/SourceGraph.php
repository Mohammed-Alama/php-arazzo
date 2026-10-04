<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources;

use Alama\Arazzo\Sources\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Sources\Resolver\Fetchers\HttpFetcher;
use Alama\Arazzo\Sources\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Sources\Resolver\SourceRegistry;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Composition root for the *sources* package.
 * It is the only place that constructs Guzzle / PSR‑HTTP objects.
 *
 * Scope note: the OpenAPI-assembling half of this class (building the
 * normalizers, the operation resolver and the document façade) moved to
 * `Alama\Arazzo\Protocol\Http\HttpSourceGraph` in F1, because those classes
 * now belong to alama/arazzo-protocol-http and this package must not name
 * them. What remains is pure source resolution: a registry plus its fetcher
 * map, which any transport can reuse.
 */
final class SourceGraph
{
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
}
