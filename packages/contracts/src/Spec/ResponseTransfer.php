<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

use Alama\Arazzo\Contracts\Interfaces\ResponseTransferInterface;

/**
 * Generic response carrier (spec D6). Default implementation of the seam;
 * per-protocol packages ship their own typed DTOs implementing the same
 * interface with concrete facets exposed as typed accessors (Phase F).
 *
 * `meta` is the generic escape hatch for execution-environment extras the
 * Arazzo spec does not define (soap.faultcode, grpc-trailer, ...).
 */
final readonly class ResponseTransfer implements ResponseTransferInterface
{
    /**
     * @param  array<string,string>  $headers
     * @param  array<string,mixed>  $views  decoded facets keyed by name
     * @param  array<string,mixed>  $meta  protocol-specific keys
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
