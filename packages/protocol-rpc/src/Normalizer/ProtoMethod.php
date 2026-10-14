<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Rpc\Normalizer;

/**
 * One `rpc` method declaration of a parsed `.proto` service (plan F5.1).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class ProtoMethod
{
    public function __construct(
        public string $name,
        public string $requestType,
        public string $responseType,
        public bool $requestStreaming = false,
        public bool $responseStreaming = false,
    ) {}
}
