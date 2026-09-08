<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Events\Interfaces;

// Framework port (kept as a seam): durable event append targets differ per deployment (DB table today).

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface EventLedgerInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function append(string $executionId, string $eventType, array $payload): void;
}
