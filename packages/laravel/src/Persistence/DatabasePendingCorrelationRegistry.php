<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Persistence;

use Alama\Arazzo\Contracts\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Contracts\Spec\PendingCorrelation;
use Illuminate\Database\ConnectionInterface;
use UnexpectedValueException;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
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
            self::textColumn($row->correlation_id, 'correlation_id'),
            self::textColumn($row->execution_id, 'execution_id'),
            self::textColumn($row->step_id, 'step_id'),
            self::textColumn($row->channel_path, 'channel_path'),
            $row->expires_at !== null && $row->expires_at instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($row->expires_at) : null,
        );
    }

    /**
     * Laravel types query-builder column values as mixed. Every column read
     * back here is written as a string by create(), so anything else means the
     * row cannot be turned into a usable PendingCorrelation and we refuse to
     * invent one.
     */
    private static function textColumn(mixed $value, string $column): string
    {
        if (!is_scalar($value)) {
            throw new UnexpectedValueException("Pending correlation column '{$column}' is not a scalar, got ".get_debug_type($value).'.');
        }

        return (string) $value;
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
