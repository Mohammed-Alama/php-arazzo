<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Contracts;

interface ExecutionRegistryInterface
{
    public function start(string $executionId, string $definitionId, string $workflowId): void;
}
