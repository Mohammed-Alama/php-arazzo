<?php

declare(strict_types=1);

namespace Alama\Arazzo\Console\Cli;

use Alama\Arazzo\Spec\Enum\ExecutionStatus;
use Alama\Arazzo\State\Interfaces\ExecutionRegistryInterface;

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
