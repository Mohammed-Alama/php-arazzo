<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

/**
 * Protocol-agnostic response seam (spec D6). Implemented by the generic
 * ResponseTransfer (contracts, A6) and by per-protocol typed DTOs (Phase F).
 * Expression/criteria resolution consumes only this seam (Phase B).
 */
interface ResponseTransferInterface
{
    /** mapped per protocol (HTTP status / RPC status object / SOAP fault status) */
    public function status(): mixed;

    /** HTTP headers / SOAP headers / RPC metadata */
    /**
     * @return array<string, string>
     */
    public function headers(): array;

    /** the transport body, undecoded */
    public function rawBody(): mixed;

    /** whether a named decoded facet is present (json/xml/proto/protocol-specific) */
    public function hasView(string $name): bool;

    /** the named decoded facet, or null when absent */
    public function view(string $name): mixed;

    /** protocol-specific keys (escape hatch) */
    /**
     * @return array<string, mixed>
     */
    public function meta(): array;
}
