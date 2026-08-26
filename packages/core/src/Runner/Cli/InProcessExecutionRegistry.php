<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Cli;

use Alama\Arazzo\Runner\Execution\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\Execution\ExecutionStatus;

/**
 * In-process registry for CLI runs: the final status lives only as long as
 * the process (and is surfaced through CliRunResult). Durable run records
 * remain an adapter concern (Laravel persists to its database).
 */
final class InProcessExecutionRegistry implements ExecutionRegistryInterface
{
    /** @var array<string, ExecutionStatus> */
    public array $statuses = [];

    public function start(string $executionId, string $definitionId, string $workflowId): void
    {
        $this->statuses[$executionId] ??= ExecutionStatus::Running;
    }

    public function complete(string $executionId, ExecutionStatus $status): void
    {
        $this->statuses[$executionId] = $status;
    }

    public function statusOf(string $executionId): ?ExecutionStatus
    {
        return $this->statuses[$executionId] ?? null;
    }
}
