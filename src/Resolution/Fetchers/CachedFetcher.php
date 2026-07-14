<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Resolution\Fetchers;

use Alama\LaravelArazzo\Resolution\SourceFetcher;
use Illuminate\Support\Facades\Cache;

final class CachedFetcher implements SourceFetcher
{
    public function __construct(
        private readonly SourceFetcher $inner,
        private readonly int $ttlSeconds = 3600,
    ) {
    }

    public function fetch(string $urlOrPath, string $basePath): string
    {
        $key = 'arazzo_source_' . md5($urlOrPath . '|' . $basePath);

        return Cache::remember($key, $this->ttlSeconds, fn (): string => $this->inner->fetch($urlOrPath, $basePath));
    }
}
