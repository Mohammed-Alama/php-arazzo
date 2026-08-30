<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Resolution\Fetchers;

use Alama\Arazzo\Resolver\Fetchers\CachedFetcher;
use Alama\Arazzo\Resolver\Interfaces\SourceFetcher;
use Psr\SimpleCache\CacheInterface;
use ReflectionProperty;

class ArrayCache implements CacheInterface
{
    private array $data = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $this->data[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->data[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->data = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return [];
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }
}

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

    $fetcher = new CachedFetcher($inner, new ArrayCache(), 3600);

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

    $fetcher = new CachedFetcher($inner, new ArrayCache(), 3600);

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

            return 'fetched-'.$urlOrPath;
        }
    };

    $fetcher = new CachedFetcher($inner, new ArrayCache(), 3600);

    $a = $fetcher->fetch('http://a.com', '');
    $b = $fetcher->fetch('http://b.com', '');

    expect($a)->toBe('fetched-http://a.com')
        ->and($b)->toBe('fetched-http://b.com')
        ->and($inner->calls)->toBe(2);
});

it('derives deterministic sha256 cache keys that vary with base path', function (): void {
    $inner = new class() implements SourceFetcher
    {
        public int $calls = 0;

        public function fetch(string $urlOrPath, string $basePath): string
        {
            $this->calls++;

            return 'content';
        }
    };

    $cache = new ArrayCache();
    $fetcher = new CachedFetcher($inner, $cache, 3600);

    $fetcher->fetch('openapi.yaml', '/base');
    $fetcher->fetch('openapi.yaml', '/base'); // same pair -> same key, one inner call
    $fetcher->fetch('openapi.yaml', '/other'); // different basePath -> different key

    expect($inner->calls)->toBe(2)
        ->and($fetcher->fetch('openapi.yaml', '/base'))->toBe('content'); // warm key still serves

    // Key shape: prefix + 64 lowercase hex chars (sha256) — not md5's 41-char form.
    $reflection = new ReflectionProperty($cache, 'data');
    $reflection->setAccessible(true);
    $storedKeys = array_keys($reflection->getValue($cache));

    expect(count($storedKeys))->toBe(2);
    foreach ($storedKeys as $key) {
        expect($key)->toMatch('/^arazzo_source_[0-9a-f]{64}$/')
            ->and(strlen($key))->toBe(78); // 'arazzo_source_' is 14 chars + 64 hex (md5 would be 46)
    }
});
