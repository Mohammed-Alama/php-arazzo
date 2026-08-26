<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Persistence;

use Alama\Arazzo\Context\PendingCorrelation;
use Alama\Arazzo\Contracts\PendingCorrelationRegistryInterface;
use Illuminate\Database\ConnectionInterface;

class DatabasePendingCorrelationRegistry implements PendingCorrelationRegistryInterface
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $table = 'arazzo_pending_correlations',
    ) {}

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath, ?int $timeoutSeconds = null): void
    {
        $this->db->table($this->table)->insert([
            'correlation_id' => $correlationId,
            'execution_id' => $executionId,
            'step_id' => $stepId,
            'channel_path' => $channelPath,
            'expires_at' => $timeoutSeconds !== null ? now()->addSeconds($timeoutSeconds) : null,
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
            $row->expires_at !== null && $row->expires_at instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($row->expires_at) : null,
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
