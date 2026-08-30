<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolver\Fetchers;

use Alama\Arazzo\Resolver\Exceptions\SourceFetchException;
use Alama\Arazzo\Resolver\Interfaces\SourceFetcher;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

final class HttpFetcher implements SourceFetcher
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
    ) {}

    public function fetch(string $urlOrPath, string $basePath): string
    {
        $url = self::resolveUrl($urlOrPath, $basePath);

        try {
            $request = $this->requestFactory->createRequest('GET', $url);
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new SourceFetchException("HTTP connection failed for {$url}: {$e->getMessage()}", 0, $e);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new SourceFetchException(
                "HTTP request failed for {$url}: ".$response->getStatusCode(),
            );
        }

        return (string) $response->getBody();
    }

    private static function resolveUrl(string $urlOrPath, string $basePath): string
    {
        if (str_starts_with($urlOrPath, 'http://') || str_starts_with($urlOrPath, 'https://')) {
            return $urlOrPath;
        }

        if (str_starts_with($basePath, 'http://') || str_starts_with($basePath, 'https://')) {
            return rtrim($basePath, '/').'/'.ltrim($urlOrPath, '/');
        }

        throw new SourceFetchException(
            "Cannot resolve relative URL '{$urlOrPath}' without an HTTP or HTTPS basePath.",
        );
    }
}
