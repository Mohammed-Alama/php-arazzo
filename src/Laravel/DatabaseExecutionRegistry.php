<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Illuminate\Database\ConnectionInterface;

class DatabaseExecutionRegistry implements ExecutionRegistryInterface
{
    public function __construct(
        private ConnectionInterface $db,
        private string $tableName = 'arazzo_executions',
    ) {
    }

    public function start(string $executionId, string $definitionId, string $workflowId): void
    {
        $this->db->table($this->tableName)->insertOrIgnore([
            'id' => $executionId,
            'definition_id' => $definitionId,
            'workflow_id' => $workflowId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
