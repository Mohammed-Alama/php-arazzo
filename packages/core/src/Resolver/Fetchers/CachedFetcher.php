<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolver\Fetchers;

use Alama\Arazzo\Resolver\SourceFetcher;
use Psr\SimpleCache\CacheInterface;

final class CachedFetcher implements SourceFetcher
{
    public function __construct(
        private readonly SourceFetcher $inner,
        private readonly CacheInterface $cache,
        private readonly int $ttlSeconds = 3600,
    ) {
    }

    public function fetch(string $urlOrPath, string $basePath): string
    {
        // Cache key only; sha256 keeps the security surface free of weak-hash flags.
        $key = 'arazzo_source_' . hash('sha256', $urlOrPath . '|' . $basePath);

        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return (string) $cached;
        }

        $fresh = $this->inner->fetch($urlOrPath, $basePath);
        $this->cache->set($key, $fresh, $this->ttlSeconds);

        return $fresh;
    }
}
