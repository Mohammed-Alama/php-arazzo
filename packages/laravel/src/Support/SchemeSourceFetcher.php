<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Support;

use Alama\Arazzo\Sources\Resolver\Exceptions\SourceFetchException;
use Alama\Arazzo\Sources\Resolver\Fetchers\HttpFetcher;
use Alama\Arazzo\Sources\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Sources\Resolver\Interfaces\SourceFetcher;
use Illuminate\Contracts\Container\Container;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Scheme-dispatching SourceFetcher for WSDL (and any other raw-source) loads
 * the SOAP resolver performs (plan F4.6).
 *
 * The core `DefaultSourceResolver` keeps its fetcher map private and resolves
 * to a decoded SourceDocument, so the SOAP slice needs its own raw fetcher:
 * `file` (and scheme-less paths) use LocalFetcher, `http`/`https` use the
 * PSR-18 client the app already exposes. Host-layer, so protocol-soap stays
 * PSR-18-clean (F4.2 ledger ruling).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class SchemeSourceFetcher implements SourceFetcher
{
    /**
     * @param  array<string, SourceFetcher>  $fetchers
     */
    public function __construct(private readonly array $fetchers) {}

    public static function fromContainer(Container $app): self
    {
        $http = static fn (): HttpFetcher => new HttpFetcher(
            $app->make(ClientInterface::class),
            $app->make(RequestFactoryInterface::class),
        );

        return new self([
            'file' => new LocalFetcher(),
            'http' => $http(),
            'https' => $http(),
        ]);
    }

    public function fetch(string $urlOrPath, string $basePath): string
    {
        $scheme = parse_url($urlOrPath, PHP_URL_SCHEME);
        if (!is_string($scheme) || $scheme === '') {
            $scheme = 'file';
        }

        $fetcher = $this->fetchers[$scheme] ?? null;
        if ($fetcher === null) {
            throw new SourceFetchException("No source fetcher is configured for scheme '{$scheme}'.");
        }

        return $fetcher->fetch($urlOrPath, $basePath);
    }
}
