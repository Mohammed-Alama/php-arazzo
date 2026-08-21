<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Events;

use DateTimeImmutable;
use Throwable;

final readonly class StepRetried
{
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public int $attempt,
        public ?Throwable $lastError,
        public DateTimeImmutable $at,
    ) {
    }
}
