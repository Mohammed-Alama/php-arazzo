<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Support;

use Alama\Arazzo\Context\PendingCorrelation;
use Alama\Arazzo\Contracts\PendingCorrelationRegistryInterface;

final class InMemoryPendingCorrelations implements PendingCorrelationRegistryInterface
{
    /** @var array<string, PendingCorrelation> */
    private array $pending = [];

    /** @var list<string> */
    public array $consumedIds = [];

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath, ?int $timeoutSeconds = null): void
    {
        $this->pending[$correlationId] = new PendingCorrelation(
            correlationId: $correlationId,
            executionId: $executionId,
            stepId: $stepId,
            channelPath: $channelPath,
            expiresAt: $timeoutSeconds === null ? null : new \DateTimeImmutable('@'.(time() + $timeoutSeconds)),
        );
    }

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        return $this->pending[$correlationId] ?? null;
    }

    public function consume(string $correlationId): void
    {
        $this->consumedIds[] = $correlationId;
        unset($this->pending[$correlationId]);
    }

    public function existsForExecution(string $executionId): bool
    {
        foreach ($this->pending as $correlation) {
            if ($correlation->executionId === $executionId) {
                return true;
            }
        }

        return false;
    }
}
