<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Resolution\Fetchers;

use Alama\LaravelArazzo\Resolution\Exceptions\SourceFetchException;
use Alama\LaravelArazzo\Resolution\SourceFetcher;
use Illuminate\Support\Facades\Http;

final class HttpFetcher implements SourceFetcher
{
    public function fetch(string $urlOrPath, string $basePath): string
    {
        $response = Http::get($urlOrPath);

        if ($response->failed()) {
            throw new SourceFetchException(
                "HTTP request failed for {$urlOrPath}: " . $response->status(),
            );
        }

        return $response->body();
    }
}
