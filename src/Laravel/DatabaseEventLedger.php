<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Illuminate\Database\ConnectionInterface;

class DatabaseEventLedger implements EventLedgerInterface
{
    public function __construct(
        private ConnectionInterface $db,
        private string $tableName = 'arazzo_events',
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function append(string $workflowId, string $eventType, array $payload): void
    {
        $this->db->table($this->tableName)->insert([
            'workflow_id' => $workflowId,
            'event_type' => $eventType,
            'payload' => json_encode($payload),
            'created_at' => now(),
        ]);
    }
}
