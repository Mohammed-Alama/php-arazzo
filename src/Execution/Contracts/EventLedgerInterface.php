<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Contracts;

interface EventLedgerInterface
{
    public function append(string $workflowId, string $eventType, array $payload): void;
}
