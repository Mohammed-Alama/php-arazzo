<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Soap\Transfer;

use Alama\Arazzo\Contracts\Interfaces\ResponseTransferInterface;

/**
 * Typed SOAP response seam (spec D6). Mirrors the generic `ResponseTransfer`
 * of contracts (A6): six accessors over constructor state, no flat xml/json
 * properties — facets live in `views`, protocol extras in `meta`.
 *
 * Key homes (documented, consumed by the SOAP executor + criteria layer):
 *   - view('xml')             = parsed DOMDocument of the response envelope
 *   - meta['soap.faultcode']  = SOAP fault code, when the envelope is a fault
 *   - meta['soap.faultstring']= SOAP fault detail, when the envelope is a fault
 *   - meta['error']           = transport/parse failure signal
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class SoapResponseTransfer implements ResponseTransferInterface
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
