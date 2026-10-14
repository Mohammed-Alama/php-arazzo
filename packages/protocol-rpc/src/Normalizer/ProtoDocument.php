<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Rpc\Normalizer;

/**
 * The parsed projection of one `.proto` document: its package name, service
 * methods, and the fields of every locally declared message (plan F5.1).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class ProtoDocument
{
    /**
     * @param  list<ProtoService>  $services
     * @param  array<string, list<ProtoField>>  $messages
     */
    public function __construct(
        public string $package,
        public array $services,
        public array $messages,
    ) {}
}
