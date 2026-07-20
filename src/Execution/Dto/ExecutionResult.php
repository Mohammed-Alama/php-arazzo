<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Dto;

class ExecutionResult
{
    public function __construct(
        public readonly string $workflowId,
        public readonly string $status,
        public readonly array $outputs,
        /** @var array<string, StepResult> */
        public readonly array $stepResults
    ) {}
}
