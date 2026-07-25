<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

final readonly class SubWorkflowResult
{
    /** @param array<string, mixed> $outputs */
    public function __construct(
        public array $outputs,
        public mixed $terminal,
        public string $childRunId,
    ) {
    }
}
