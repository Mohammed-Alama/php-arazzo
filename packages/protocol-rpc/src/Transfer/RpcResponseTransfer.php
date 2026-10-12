<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Rpc\Transfer;

use Alama\Arazzo\Contracts\Interfaces\ResponseTransferInterface;

/**
 * Typed RPC response seam (spec D6). Mirrors the generic `ResponseTransfer`
 * of contracts (A6): six accessors over constructor state, no flat proto/json
 * properties — facets live in `views`, protocol extras in `meta`.
 *
 * Key homes (documented, consumed by the RPC executor + criteria layer):
 *   - status()               = `$response.status#/code`: the integer grpc
 *                              status (0 = OK) for grpc/grpc-web when a
 *                              `grpc-status` header is present, the string
 *                              error code for twirp/connect when their error
 *                              header is present, else the HTTP status.
 *   - view('proto')          = decoded protobuf message, as an assoc map
 *   - view('json')           = json-decoded body, for JSON content-types
 *   - meta['rpc.error']      = RPC error code (int for grpc, string for
 *                              twirp/connect) when the call failed
 *   - meta['rpc.message']    = error detail (grpc-message / body)
 *   - meta['error']          = transport/parse/unframing failure signal
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class RpcResponseTransfer implements ResponseTransferInterface
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $views
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        private mixed $status,
        private array $headers,
        private mixed $rawBody,
        private array $views = [],
        private array $meta = [],
    ) {}

    public function status(): mixed
    {
        return $this->status;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function rawBody(): mixed
    {
        return $this->rawBody;
    }

    public function hasView(string $name): bool
    {
        return array_key_exists($name, $this->views);
    }

    public function view(string $name): mixed
    {
        return $this->views[$name] ?? null;
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return $this->meta;
    }
}
