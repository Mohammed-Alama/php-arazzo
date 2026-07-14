<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Resolution\Fetchers;

use Alama\LaravelArazzo\Resolution\Fetchers\CachedFetcher;
use Alama\LaravelArazzo\Resolution\SourceFetcher;

it('returns the fetched content', function (): void {
    $inner = new class() implements SourceFetcher
    {
        public int $calls = 0;

        public function fetch(string $urlOrPath, string $basePath): string
        {
            $this->calls++;

            return 'fetched';
        }
    };

    $fetcher = new CachedFetcher($inner, 3600);

    expect($fetcher->fetch('http://test.com', ''))->toBe('fetched');
});

it('calls the inner fetcher only once on repeated requests', function (): void {
    $inner = new class() implements SourceFetcher
    {
        public int $calls = 0;

        public function fetch(string $urlOrPath, string $basePath): string
        {
            $this->calls++;

            return 'fetched';
        }
    };

    $fetcher = new CachedFetcher($inner, 3600);

    $fetcher->fetch('http://test.com', '');
    $fetcher->fetch('http://test.com', '');

    expect($inner->calls)->toBe(1);
});

it('calls the inner fetcher separately for different urls', function (): void {
    $inner = new class() implements SourceFetcher
    {
        public int $calls = 0;

        public function fetch(string $urlOrPath, string $basePath): string
        {
            $this->calls++;

            return 'fetched-' . $urlOrPath;
        }
    };

    $fetcher = new CachedFetcher($inner, 3600);

    $a = $fetcher->fetch('http://a.com', '');
    $b = $fetcher->fetch('http://b.com', '');

    expect($a)->toBe('fetched-http://a.com')
        ->and($b)->toBe('fetched-http://b.com')
        ->and($inner->calls)->toBe(2);
});
