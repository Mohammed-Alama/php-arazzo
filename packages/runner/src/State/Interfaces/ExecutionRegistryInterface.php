<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\State\Interfaces;

use Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus;

// Framework port (kept as a seam): run registry persistence is adapter-specific.

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface ExecutionRegistryInterface
{
    public function start(string $executionId, string $definitionId, string $workflowId): void;

    public function complete(string $executionId, ExecutionStatus $status): void;
}
