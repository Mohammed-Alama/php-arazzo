<?php

namespace Alama\LaravelArazzo\Laravel;

use Illuminate\Database\ConnectionInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\PendingCorrelation;
use stdClass;

class DatabasePendingCorrelationRegistry implements PendingCorrelationRegistryInterface
{
    public function __construct(
        private readonly ConnectionInterface $db
    ) {
    }

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void
    {
        $this->db->table('arazzo_pending_correlations')->insert([
            'correlation_id' => $correlationId,
            'execution_id' => $executionId,
            'step_id' => $stepId,
            'channel_path' => $channelPath,
            'created_at' => now(),
        ]);
    }

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        $row = $this->db->table('arazzo_pending_correlations')
            ->where('correlation_id', $correlationId)
            ->first();

        if (! $row) {
            return null;
        }

        return new PendingCorrelation(
            $row->correlation_id,
            $row->execution_id,
            $row->step_id,
            $row->channel_path
        );
    }

    public function consume(string $correlationId): void
    {
        $this->db->table('arazzo_pending_correlations')
            ->where('correlation_id', $correlationId)
            ->delete();
    }

    public function existsForExecution(string $executionId): bool
    {
        return $this->db->table('arazzo_pending_correlations')
            ->where('execution_id', $executionId)
            ->exists();
    }
}
