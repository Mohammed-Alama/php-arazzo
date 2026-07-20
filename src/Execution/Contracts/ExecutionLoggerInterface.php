<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Contracts;

use Throwable;

interface ExecutionLoggerInterface
{
    public function logStepStarted(string $stepId): void;

    public function logStepCompleted(string $stepId, array $outputs): void;

    public function logStepFailed(string $stepId, Throwable $error): void;
}
