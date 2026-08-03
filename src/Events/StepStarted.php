<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Events;

final readonly class StepStarted
{
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public int $attempt,
        public \DateTimeImmutable $at,
    ) {
    }
}
