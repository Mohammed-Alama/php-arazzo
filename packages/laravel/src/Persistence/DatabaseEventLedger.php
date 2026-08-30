<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Persistence;

use Alama\Arazzo\Events\Interfaces\EventLedgerInterface;
use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class DatabaseEventLedger implements EventLedgerInterface
{
    public function __construct(
        private ConnectionInterface $db,
        private string $tableName = 'arazzo_events',
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function append(string $executionId, string $eventType, array $payload): void
    {
        try {
            $this->db->table($this->tableName)->insert([
                'execution_id' => $executionId,
                'event_type' => $eventType,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            $this->logger?->warning("Failed to append event '{$eventType}' for execution '{$executionId}': {$e->getMessage()}");
        }
    }
}
