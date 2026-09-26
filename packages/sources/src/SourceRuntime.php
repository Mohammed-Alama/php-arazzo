<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources;

use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;

/**
 * A document and the resolver behind it, produced by a single
 * SourceGraph::runtime() call.
 *
 * Consumers that need cebe operation handles -- the runner's three
 * OpenAPI-specific collaborators -- need the resolver as well as the
 * document. Handing out the pair is what keeps them from building a second
 * resolver, and with it a second fetcher map and a second transport.
 */
final readonly class SourceRuntime
{
    public function __construct(
        public Document $document,
        public OpenApiOperationResolver $operations,
    ) {}
}
