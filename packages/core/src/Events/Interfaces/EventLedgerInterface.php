<?php

declare(strict_types=1);

namespace Alama\Arazzo\Events\Interfaces;

// Framework port (kept as a seam): durable event append targets differ per deployment (DB table today).

interface EventLedgerInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function append(string $executionId, string $eventType, array $payload): void;
}
