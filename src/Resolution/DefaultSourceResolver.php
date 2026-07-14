<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Resolution;

use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Resolution\Exceptions\SourceFetchException;
use Alama\LaravelArazzo\Resolution\Exceptions\SourceParseException;

final readonly class DefaultSourceResolver implements SourceResolver
{
    public function __construct(
        /** @var array<string, SourceFetcher> */
        private array $fetchers,
        /** @var array<string, SourceParser> */
        private array $parsers,
    ) {
    }

    public function resolve(SourceDescription $source, string $basePath): ResolvedSource
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

        $parser = $this->parsers[$source->type->value] ?? null;
        if ($parser === null) {
            throw new SourceParseException("No parser configured for source type '{$source->type->value}'.");
        }

        return $parser->parse($content);
    }
}
