<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Resolution\Fetchers;

use Alama\LaravelArazzo\Resolution\Exceptions\SourceFetchException;
use Alama\LaravelArazzo\Resolution\SourceFetcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class HttpFetcher implements SourceFetcher
{
    public function fetch(string $urlOrPath, string $basePath): string
    {
        $url = self::resolveUrl($urlOrPath, $basePath);

        try {
            $response = Http::get($url);
        } catch (ConnectionException $e) {
            throw new SourceFetchException("HTTP connection failed for {$url}: {$e->getMessage()}", 0, $e);
        }

        if ($response->failed()) {
            throw new SourceFetchException(
                "HTTP request failed for {$url}: " . $response->status(),
            );
        }

        return $response->body();
    }

    private static function resolveUrl(string $urlOrPath, string $basePath): string
    {
        if (str_starts_with($urlOrPath, 'http://') || str_starts_with($urlOrPath, 'https://')) {
            return $urlOrPath;
        }

        if (str_starts_with($basePath, 'http://') || str_starts_with($basePath, 'https://')) {
            return rtrim($basePath, '/') . '/' . ltrim($urlOrPath, '/');
        }

        throw new SourceFetchException(
            "Cannot resolve relative URL '{$urlOrPath}' without an HTTP or HTTPS basePath.",
        );
    }
}
