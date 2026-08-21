<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

final readonly class SubWorkflowResult
{
    /** @param array<string, mixed> $outputs */
    public function __construct(
        public array $outputs,
        public string $status,
        public string $childRunId,
    ) {
    }
}
