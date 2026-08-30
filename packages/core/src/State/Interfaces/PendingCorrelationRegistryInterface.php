<?php

declare(strict_types=1);

namespace Alama\Arazzo\State\Interfaces;

use Alama\Arazzo\Spec\PendingCorrelation;

// Framework port (kept as a seam): pending correlations persist per deployment (DB table today).

interface PendingCorrelationRegistryInterface
{
    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath, ?int $timeoutSeconds = null): void;

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation;

    public function consume(string $correlationId): void;

    public function existsForExecution(string $executionId): bool;
}
