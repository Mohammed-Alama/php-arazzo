<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Support;

use Alama\Arazzo\Spec\ExecutionStatus;
use Alama\Arazzo\State\Interfaces\ExecutionRegistryInterface;

final class RecordingExecutionRegistry implements ExecutionRegistryInterface
{
    /** @var list<array{executionId: string, definitionId: string, workflowId: string}> */
    public array $started = [];

    /** @var list<array{executionId: string, status: ExecutionStatus}> */
    public array $completed = [];

    public function start(string $executionId, string $definitionId, string $workflowId): void
    {
        $this->started[] = ['executionId' => $executionId, 'definitionId' => $definitionId, 'workflowId' => $workflowId];
    }

    public function complete(string $executionId, ExecutionStatus $status): void
    {
        $this->completed[] = ['executionId' => $executionId, 'status' => $status];
    }
}
