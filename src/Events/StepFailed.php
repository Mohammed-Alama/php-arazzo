<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Events;

final readonly class StepFailed
{
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public \Throwable $cause,
        public \DateTimeImmutable $at,
    ) {
    }
}
