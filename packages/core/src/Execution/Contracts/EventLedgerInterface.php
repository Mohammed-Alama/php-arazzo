<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution\Contracts;

interface EventLedgerInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function append(string $executionId, string $eventType, array $payload): void;
}
