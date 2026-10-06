<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources;

use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolverInterface;

/**
 * A document and the resolver behind it, produced by a single
 * SourceGraph::runtime() call.
 *
 * Consumers that need cebe operation handles -- the runner's three
 * OpenAPI-specific collaborators -- need the resolver as well as the
 * document. Handing out the pair is what keeps them from building a second
 * resolver, and with it a second fetcher map and a second transport. What
 * they get is the interface, so this package never names a protocol type.
 *
 * Generic in the resolver so that a caller which *does* hold the concrete
 * protocol resolver keeps it: `SourceGraph::runtime()` returns
 * `SourceRuntime<OpenApiOperationResolver>`, so `$runtime->operations` stays
 * concrete for the runner without this package importing protocol-http.
 *
 * @template TResolver of OpenApiOperationResolverInterface
 */
final readonly class SourceRuntime
{
    /**
     * @param  TResolver  $operations
     */
    public function __construct(
        public DocumentInterface $document,
        public SourceLoader $loader,
        public OpenApiOperationResolverInterface $operations,
    ) {}
}
