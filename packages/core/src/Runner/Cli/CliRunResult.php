<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Cli;

use Alama\Arazzo\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\Execution\ExecutionStatus;

/**
 * Terminal snapshot of a CLI run: the registry status at drain end, plus
 * whether steps remain suspended (resume-worthy).
 */
final class CliRunResult
{
    public function __construct(
        public readonly string $executionId,
        public readonly string $status,
        public readonly bool $suspended,
    ) {}

    public static function fromStatus(string $executionId, ExecutionRegistryInterface $registry): self
    {
        $status = null;

        if ($registry instanceof InProcessExecutionRegistry) {
            $status = $registry->statusOf($executionId);
        }

        return new self(
            executionId: $executionId,
            status: $status !== null ? $status->value : 'running',
            suspended: ($status === null || $status === ExecutionStatus::Running),
        );
    }

    public function succeeded(): bool
    {
        return $this->status === 'succeeded';
    }

    public function failed(): bool
    {
        return $this->status === 'failed';
    }
}
