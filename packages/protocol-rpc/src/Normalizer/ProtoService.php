<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Rpc\Normalizer;

/**
 * A `service` block parsed from a `.proto` document (plan F5.1).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class ProtoService
{
    /**
     * @param  list<ProtoMethod>  $methods
     */
    public function __construct(
        public string $name,
        public array $methods,
    ) {}
}
