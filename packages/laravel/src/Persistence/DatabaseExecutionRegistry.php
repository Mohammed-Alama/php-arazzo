<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Persistence;

use Alama\Arazzo\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Execution\ExecutionStatus;
use Illuminate\Database\ConnectionInterface;

class DatabaseExecutionRegistry implements ExecutionRegistryInterface
{
    public function __construct(
        private ConnectionInterface $db,
        private string $tableName = 'arazzo_executions',
    ) {}

    public function start(string $executionId, string $definitionId, string $workflowId): void
    {
        $this->db->table($this->tableName)->insertOrIgnore([
            'id' => $executionId,
            'definition_id' => $definitionId,
            'workflow_id' => $workflowId,
            'status' => ExecutionStatus::Running->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function complete(string $executionId, ExecutionStatus $status): void
    {
        $this->db->table($this->tableName)
            ->where('id', $executionId)
            ->where('status', ExecutionStatus::Running->value)
            ->update([
                'status' => $status->value,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
