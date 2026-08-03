<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution\Contracts;

use Alama\Arazzo\Execution\PendingCorrelation;

interface PendingCorrelationRegistryInterface
{
    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void;

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation;

    public function consume(string $correlationId): void;

    public function existsForExecution(string $executionId): bool;
}
