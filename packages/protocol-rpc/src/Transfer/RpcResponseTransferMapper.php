<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Rpc\Transfer;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Protocol\Rpc\Protocol\RpcMessageCodec;
use Alama\Arazzo\Protocol\Rpc\Protocol\RpcWireProfile;
use Psr\Http\Message\ResponseInterface;

/**
 * Maps a raw PSR-7 RPC response into an `RpcResponseTransfer` running the
 * four-variant status rules (plan F5.1):
 *
 *   - grpc/grpc-web: a `grpc-status` header yields the integer status code
 *     (0 = OK, non-zero = meta['rpc.error'] + grpc-message detail).
 *   - twirp: an `X-Twirp-Error` header yields the string error code.
 *   - connect: a `Connect-Error-Code` header yields the string error code.
 *   - without an RPC code: a non-2xx HTTP status becomes meta['error'].
 *
 * Body views: JSON content-types decode into view('json'); everything else
 * is a (un)framed protobuf payload decoded with the described response fields
 * into view('proto'). Unreadable bodies set meta['error'].
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RpcResponseTransferMapper
{
    public function __construct(
        private readonly RpcMessageCodec $codec,
    ) {}

    /**
     * @param  list<array{name: string, type: string, number: int, repeated: bool}>  $responseFields
     */
    public function map(ResponseInterface $response, RpcProtocol $protocol, array $responseFields): RpcResponseTransfer
    {
        $headers = $this->flattenHeaders($response->getHeaders());
        $rawBody = (string) $response->getBody();
        $profile = RpcWireProfile::fromProtocol($protocol);

        [$status, $meta] = $this->statusOf($profile, $headers, $response->getStatusCode(), $rawBody);
        [$views, $meta] = $this->viewsOf($profile, $headers, $rawBody, $meta, $responseFields);

        return new RpcResponseTransfer($status, $headers, $rawBody, $views, $meta);
    }

    /**
     * Runs the four-variant status rules: an RPC error code header sets the
     * transfer status (integer for grpc/grpc-web, string for twirp/connect),
     * else a non-2xx HTTP status becomes meta['error'].
     *
     * @param  array<string, string>  $headers
     * @return array{0: int|string, 1: array<string, mixed>}
     */
    private function statusOf(RpcWireProfile $profile, array $headers, int $httpStatus, string $rawBody): array
    {
        $errorCode = $this->firstHeader($headers, $profile->statusHeaders());

        if ($errorCode === null) {
            return $httpStatus >= 400
                ? [$httpStatus, ['error' => "HTTP {$httpStatus}"]]
                : [$httpStatus, []];
        }

        if ($profile->statusIsInteger()) {
            $status = (int) $errorCode;

            return $status === 0
                ? [0, []]
                : [$status, ['rpc.error' => $status, 'rpc.message' => (string) ($headers['grpc-message'] ?? $rawBody)]];
        }

        return [$errorCode, ['rpc.error' => $errorCode, 'rpc.message' => $rawBody === '' ? null : $rawBody]];
    }

    /**
     * Decodes the body into views: JSON content-types into view('json'),
     * framed/raw protobuf into view('proto'). Empty bodies and RPC-failed
     * bodies (meta['rpc.error']) carry no views; an unframeable body adds
     * meta['error'].
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $meta
     * @param  list<array{name: string, type: string, number: int, repeated: bool}>  $responseFields
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function viewsOf(RpcWireProfile $profile, array $headers, string $rawBody, array $meta, array $responseFields): array
    {
        if ($rawBody === '' || isset($meta['rpc.error'])) {
            return [[], $meta];
        }

        $contentType = strtolower((string) ($headers['content-type'] ?? ''));

        if (str_contains($contentType, 'json')) {
            $decoded = json_decode($rawBody, true);

            return [['json' => is_array($decoded) ? $decoded : ['raw' => $rawBody]], $meta];
        }

        $message = $profile->framesMessages() ? ($this->codec->unframe($rawBody) ?? null) : $rawBody;

        if ($message === null) {
            return [[], $meta + ['error' => 'The RPC response body could not be unframed.']];
        }

        return [['proto' => $this->codec->decodeBinary($responseFields, $message)], $meta];
    }

    /**
     * @param  array<string, string>  $headers
     * @param  list<string>  $names
     */
    private function firstHeader(array $headers, array $names): ?string
    {
        foreach ($names as $name) {
            if (isset($headers[$name]) && $headers[$name] !== '') {
                return $headers[$name];
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, array<string>>  $headers
     * @return array<string, string>
     */
    private function flattenHeaders(array $headers): array
    {
        $flattened = [];
        foreach ($headers as $name => $values) {
            $first = ($values[array_key_first($values)] ?? '');

            $flattened[strtolower((string) $name)] = (string) $first;
        }

        return $flattened;
    }
}
