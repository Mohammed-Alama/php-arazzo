<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution\Data;

class ExecutionResult
{
    /**
     * @param  array<string, mixed>  $outputs
     * @param  array<string, StepResult>  $stepResults
     * @param  list<string>  $workflowCallStack
     */
    public function __construct(
        public readonly string $workflowId,
        public readonly string $status,
        public readonly array $outputs,
        public readonly array $stepResults,
        public readonly int $stepsSpent = 0,
        public readonly array $workflowCallStack = [],
    ) {}
}
