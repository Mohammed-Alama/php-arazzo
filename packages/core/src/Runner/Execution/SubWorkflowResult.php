<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

final readonly class SubWorkflowResult
{
    /**
     * @param array<string, mixed> $outputs
     * @param array<string, mixed> $inputs
     * @param list<string> $workflowCallStack
     */
    public function __construct(
        public array $outputs,
        public string $status,
        public string $childRunId,
        public array $inputs = [],
        public int $stepsSpent = 0,
        public array $workflowCallStack = [],
    ) {
    }
}
