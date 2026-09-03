<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Resolver;

use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\SourceDocument;
use Alama\Arazzo\Document\Parser\Decoders\NativeJsonDecoder;
use Alama\Arazzo\Document\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Document\Resolver\Exceptions\SourceFetchException;
use Alama\Arazzo\Document\Resolver\Exceptions\SourceParseException;
use Alama\Arazzo\Document\Resolver\Interfaces\SourceFetcher;
use Alama\Arazzo\Document\Resolver\Interfaces\SourceResolver;
use Throwable;

final readonly class DefaultSourceResolver implements SourceResolver
{
    public function __construct(
        /** @var array<string, SourceFetcher> */
        private array $fetchers,
    ) {}

    public function resolve(SourceDescription $source, string $basePath): SourceDocument
    {
        $scheme = parse_url($source->url, PHP_URL_SCHEME);
        if (!is_string($scheme) || $scheme === '') {
            $scheme = 'file';
        }

        $fetcher = $this->fetchers[$scheme] ?? null;
        if ($fetcher === null) {
            throw new SourceFetchException("No fetcher configured for scheme '{$scheme}'.");
        }

        $content = $fetcher->fetch($source->url, $basePath);

        $isYaml = !str_starts_with(trim($content), '{');
        try {
            $decoded = $isYaml
                ? (new SymfonyYamlDecoder())->decode($content)
                : (new NativeJsonDecoder())->decode($content);
        } catch (Throwable $e) {
            throw new SourceParseException("Failed to parse source '{$source->name}': ".$e->getMessage(), 0, $e);
        }

        if (!is_array($decoded)) {
            throw new SourceParseException("Parsed document for '{$source->name}' must be an array.");
        }

        // Canonical URI determination can be simple for now.
        // If it's a file scheme, and relative, it should be resolved against basePath.
        $canonicalUri = $this->resolveCanonicalUri($source->url, $basePath);

        /** @var array<string, mixed> $decoded */
        return new SourceDocument(
            name: $source->name,
            type: $source->type,
            canonicalUri: $canonicalUri,
            content: $decoded,
        );
    }

    private function resolveCanonicalUri(string $url, string $basePath): string
    {
        $url = str_replace('\\', '/', $url);
        $basePath = str_replace('\\', '/', $basePath);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (is_string($scheme) && $scheme !== '') {
            return $url; // Already absolute with scheme
        }

        // Relative file path
        if (str_starts_with($url, '/')) {
            return 'file://'.$url;
        }

        $base = rtrim($basePath, '/');

        // Let's do basic relative path resolution
        $path = $base.'/'.$url;

        // Resolve . and ..
        $parts = explode('/', $path);
        $resolved = [];
        foreach ($parts as $part) {
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..') {
                array_pop($resolved);
            } else {
                $resolved[] = $part;
            }
        }

        return 'file:///'.implode('/', $resolved);
    }
}
