<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Rpc\Normalizer;

/**
 * A single message field declaration parsed from a `.proto` document
 * (plan F5.1). Scalar wire types carry numbers the RpcMessageEncoder maps
 * onto the protobuf binary format.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class ProtoField
{
    public function __construct(
        public string $name,
        public string $type,
        public int $number,
        public bool $repeated = false,
    ) {}
}
