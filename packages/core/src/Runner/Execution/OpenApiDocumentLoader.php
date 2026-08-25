<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Resolver\SourceResolver;
use Alama\Arazzo\Spec\SourceDescription;
use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
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
            // Plain readFromJson() leaves References WITHOUT a resolution
            // context; walk the tree once so lazy ->resolve() calls work.
            $openapi->resolveReferences(new ReferenceContext(
                $openapi,
                $resolvedSource->canonicalUri !== '' ? $resolvedSource->canonicalUri : 'memory://source',
            ));

            $this->cache[$sourceDesc->name] = $openapi;

            return $openapi;
        } catch (Throwable) {
            return null;
        }
    }
}
