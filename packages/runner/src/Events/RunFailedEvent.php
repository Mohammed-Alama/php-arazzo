<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Events;

use DateTimeImmutable;
use Throwable;

final readonly class RunFailedEvent
{
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public Throwable $cause,
        public DateTimeImmutable $at,
        public string $category = 'execution',
    ) {}
}
