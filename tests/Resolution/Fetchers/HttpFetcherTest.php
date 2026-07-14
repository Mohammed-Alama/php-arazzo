<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Resolution\Fetchers;

use Alama\LaravelArazzo\Resolution\Exceptions\SourceFetchException;
use Alama\LaravelArazzo\Resolution\Fetchers\HttpFetcher;
use Illuminate\Support\Facades\Http;

it('fetches content from an http url', function (): void {
    Http::fake(['*' => Http::response('remote content', 200)]);

    $fetcher = new HttpFetcher();
    $content = $fetcher->fetch('https://example.com/api.json', '');

    expect($content)->toBe('remote content');
});

it('throws SourceFetchException on http error response', function (): void {
    Http::fake(['*' => Http::response('Not Found', 404)]);

    $fetcher = new HttpFetcher();
    $fetcher->fetch('https://example.com/api.json', '');
})->throws(SourceFetchException::class);
