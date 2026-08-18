<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Contracts;

use Throwable;

interface ExecutionLoggerInterface
{
    public function logStepStarted(string $stepId): void;

    /**
     * @param array<string, mixed> $outputs
     */
    public function logStepCompleted(string $workflowId, string $stepId, array $outputs): void;

    public function logStepFailed(string $stepId, Throwable $error): void;
}
