<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Events;

use DateTimeImmutable;
use Throwable;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class StepRetriedEvent
{
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public int $attempt,
        public ?Throwable $lastError,
        public DateTimeImmutable $at,
    ) {}
}
