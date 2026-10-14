<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Rpc\Protocol;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;

/**
 * The four-variant dispatch table (plan F5.1): turns the step's rpcProtocol
 * discriminator into the wire behaviour of one protocol package.
 *
 *   variant   | canonical request Content-Type | framing | status kind | error-code wire home
 *   grpc      | application/grpc               | 5-byte  | int         | grpc-status
 *   grpc-web  | application/grpc-web+proto     | 5-byte  | int         | grpc-status
 *   twirp     | application/protobuf           | raw     | string      | X-Twirp-Error
 *   connect   | application/connect+proto      | raw     | string      | Connect-Error-Code
 *
 * The per-variant alternatives (e.g. JSON bodies for twirp/connect, `+json`
 * suffixes for grpc) are documented in
 * docs/research/2026-09-08-arazzo-protocol-spec-prs-impact.md and deferred —
 * every variant runs binary protobuf bodies through one encode/decode path.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
enum RpcWireProfile: string
{
    case Grpc = 'grpc';

    case GrpcWeb = 'grpc-web';

    case Twirp = 'twirp';

    case Connect = 'connect';

    public static function fromProtocol(RpcProtocol $protocol): self
    {
        return match ($protocol) {
            RpcProtocol::Grpc => self::Grpc,
            RpcProtocol::GrpcWeb => self::GrpcWeb,
            RpcProtocol::Twirp => self::Twirp,
            RpcProtocol::Connect => self::Connect,
        };
    }

    /**
     * The canonical request Content-Type emitted by RpcRequestBuilder.
     */
    public function requestContentType(): string
    {
        return match ($this) {
            self::Grpc => 'application/grpc',
            self::GrpcWeb => 'application/grpc-web+proto',
            self::Twirp => 'application/protobuf',
            self::Connect => 'application/connect+proto',
        };
    }

    /**
     * Whether the body carries the 5-byte gRPC message prefix (flag + length).
     */
    public function framesMessages(): bool
    {
        return $this === self::Grpc || $this === self::GrpcWeb;
    }

    /**
     * Whether `$response.status#/code` is an integer code (grpc-style
     * `grpc-status`) rather than a string error code (twirp/connect).
     */
    public function statusIsInteger(): bool
    {
        return $this === self::Grpc || $this === self::GrpcWeb;
    }

    /**
     * Header names to consult, in order, for the RPC status/error code.
     *
     * @return list<string>
     */
    public function statusHeaders(): array
    {
        return match ($this) {
            self::Grpc, self::GrpcWeb => ['grpc-status'],
            self::Twirp => ['x-twirp-error'],
            self::Connect => ['connect-error-code'],
        };
    }
}
