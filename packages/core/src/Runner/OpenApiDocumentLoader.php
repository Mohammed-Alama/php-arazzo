<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Resolver\SourceResolver;
use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use Throwable;

final class OpenApiDocumentLoader
{
    /** @var array<string, OpenApi> */
    private array $cache = [];

    public function __construct(private readonly SourceResolver $sourceResolver)
    {
    }

    public function load(SourceDescription $sourceDesc, string $basePath): ?OpenApi
    {
        if (isset($this->cache[$sourceDesc->name])) {
            return $this->cache[$sourceDesc->name];
        }

        $resolvedSource = $this->sourceResolver->resolve($sourceDesc, $basePath);
        $extracted = $resolvedSource->content;

        $json = json_encode($extracted);
        if ($json === false) {
            return null;
        }

        try {
            $openapi = Reader::readFromJson($json);
            $this->cache[$sourceDesc->name] = $openapi;

            return $openapi;
        } catch (Throwable) {
            return null;
        }
    }
}
