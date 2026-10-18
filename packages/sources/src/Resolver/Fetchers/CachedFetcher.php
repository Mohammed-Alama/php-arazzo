<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources\Resolver\Fetchers;

use Alama\Arazzo\Sources\Resolver\Interfaces\SourceFetcher;
use Psr\SimpleCache\CacheInterface;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class CachedFetcher implements SourceFetcher
{
    public function __construct(
        private readonly SourceFetcher $inner,
        private readonly CacheInterface $cache,
        private readonly int $ttlSeconds = 3600,
    ) {}

    public function fetch(string $urlOrPath, string $basePath): string
    {
        // Cache key only; sha256 keeps the security surface free of weak-hash flags.
        $key = 'arazzo_source_'.hash('sha256', $urlOrPath.'|'.$basePath);

        $cached = $this->cache->get($key);
        if (is_scalar($cached)) {
            return (string) $cached;
        }

        // Anything else is a store that handed back a shape we never wrote
        // (a decoded array, an object); treat it as cold and refetch.
        $fresh = $this->inner->fetch($urlOrPath, $basePath);
        $this->cache->set($key, $fresh, $this->ttlSeconds);

        return $fresh;
    }
}
