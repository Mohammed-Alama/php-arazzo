<?php

namespace Alama\LaravelArazzo\Execution\Contracts;

use Alama\LaravelArazzo\Execution\PendingCorrelation;

interface PendingCorrelationRegistryInterface
{
    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void;
    public function findByCorrelationId(string $correlationId): ?PendingCorrelation;
    public function consume(string $correlationId): void;
    public function existsForExecution(string $executionId): bool;
}
