<?php

declare(strict_types=1);

namespace Alama\Arazzo\Interfaces;

use Alama\Arazzo\Spec\ExecutionStatus;

// Framework port (kept as a seam): run registry persistence is adapter-specific.

interface ExecutionRegistryInterface
{
    public function start(string $executionId, string $definitionId, string $workflowId): void;

    public function complete(string $executionId, ExecutionStatus $status): void;
}
