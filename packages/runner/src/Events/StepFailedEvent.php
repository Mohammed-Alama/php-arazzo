<?php

declare(strict_types=1);

namespace Alama\Arazzo\Events;

use DateTimeImmutable;
use Throwable;

final readonly class StepFailedEvent
{
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public Throwable $cause,
        public DateTimeImmutable $at,
        public string $category = 'execution',
    ) {}
}
