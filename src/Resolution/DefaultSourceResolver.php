<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Resolution;

use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Resolution\Exceptions\SourceParseException;

final readonly class DefaultSourceResolver implements SourceResolver
{
    public function __construct(
        private SourceFetcher $remoteFetcher,
        private SourceFetcher $localFetcher,
        /** @var array<string, SourceParser> $parsers */
        private array $parsers,
    ) {
    }

    public function resolve(SourceDescription $source, string $basePath): ResolvedSource
    {
        $isRemote = str_starts_with($source->url, 'http://') || str_starts_with($source->url, 'https://');
        $fetcher = $isRemote ? $this->remoteFetcher : $this->localFetcher;

        $content = $fetcher->fetch($source->url, $basePath);

        $parser = $this->parsers[$source->type->value] ?? null;
        if ($parser === null) {
            throw new SourceParseException("No parser configured for source type '{$source->type->value}'.");
        }

        return $parser->parse($content);
    }
}
