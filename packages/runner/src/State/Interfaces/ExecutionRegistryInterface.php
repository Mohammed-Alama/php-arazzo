<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\State\Interfaces;

use Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus;

// Framework port (kept as a seam): run registry persistence is adapter-specific.

interface ExecutionRegistryInterface
{
    public function start(string $executionId, string $definitionId, string $workflowId): void;

    public function complete(string $executionId, ExecutionStatus $status): void;
}
