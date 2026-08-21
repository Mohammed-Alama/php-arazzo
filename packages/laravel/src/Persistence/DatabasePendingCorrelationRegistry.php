<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Persistence;

use Alama\Arazzo\Runner\Context\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\Context\PendingCorrelation;
use Illuminate\Database\ConnectionInterface;

class DatabasePendingCorrelationRegistry implements PendingCorrelationRegistryInterface
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $table = 'arazzo_pending_correlations',
    ) {
    }

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void
    {
        $this->db->table($this->table)->insert([
            'correlation_id' => $correlationId,
            'execution_id' => $executionId,
            'step_id' => $stepId,
            'channel_path' => $channelPath,
            'created_at' => now(),
        ]);
    }

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        $row = $this->db->table($this->table)
            ->where('correlation_id', $correlationId)
            ->first();

        if (!$row) {
            return null;
        }

        return new PendingCorrelation(
            $row->correlation_id,
            $row->execution_id,
            $row->step_id,
            $row->channel_path,
        );
    }

    public function consume(string $correlationId): void
    {
        $this->db->table($this->table)
            ->where('correlation_id', $correlationId)
            ->delete();
    }

    public function existsForExecution(string $executionId): bool
    {
        return $this->db->table($this->table)
            ->where('execution_id', $executionId)
            ->exists();
    }
}
