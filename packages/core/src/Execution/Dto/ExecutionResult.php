<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution\Dto;

class ExecutionResult
{
    /**
     * @param array<string, mixed> $outputs
     * @param array<string, StepResult> $stepResults
     */
    public function __construct(
        public readonly string $workflowId,
        public readonly string $status,
        public readonly array $outputs,
        public readonly array $stepResults,
    ) {
    }
}
