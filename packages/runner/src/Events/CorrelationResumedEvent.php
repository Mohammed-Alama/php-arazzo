<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Events;

use DateTimeImmutable;

final readonly class CorrelationResumedEvent
{
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public string $correlationId,
        public DateTimeImmutable $at,
    ) {}
}
