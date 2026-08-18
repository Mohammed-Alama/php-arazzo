<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolver\Fetchers;

use Alama\Arazzo\Resolver\Exceptions\SourceFetchException;
use Alama\Arazzo\Resolver\SourceFetcher;

final class LocalFetcher implements SourceFetcher
{
    public function fetch(string $urlOrPath, string $basePath): string
    {
        $path = $this->isAbsolute($urlOrPath)
            ? $urlOrPath
            : rtrim($basePath, '/\\') . '/' . ltrim($urlOrPath, '/\\');

        $content = @file_get_contents($path);

        if ($content === false) {
            throw new SourceFetchException("Failed to read local file: {$path}");
        }

        return $content;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }
}
