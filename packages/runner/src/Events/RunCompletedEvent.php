<?php

declare(strict_types=1);

namespace Alama\Arazzo\Events;

use DateTimeImmutable;

final readonly class RunCompletedEvent
{
    /**
     * @param  array<string, mixed>  $outputs
     */
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public array $outputs,
        public DateTimeImmutable $at,
    ) {}
}
