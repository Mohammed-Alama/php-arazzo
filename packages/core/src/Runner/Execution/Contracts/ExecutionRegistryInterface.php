<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution\Contracts;

// Framework port (kept as a seam): run registry persistence is adapter-specific.

use Alama\Arazzo\Runner\Execution\ExecutionStatus;

interface ExecutionRegistryInterface
{
    public function start(string $executionId, string $definitionId, string $workflowId): void;

    public function complete(string $executionId, ExecutionStatus $status): void;
}
