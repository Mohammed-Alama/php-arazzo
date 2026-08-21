<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Events;

use DateTimeImmutable;

final readonly class CorrelationPending
{
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public string $correlationId,
        public string $channelPath,
        public DateTimeImmutable $at,
    ) {
    }
}
