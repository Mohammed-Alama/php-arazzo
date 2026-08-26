<?php

declare(strict_types=1);

namespace Alama\Arazzo\Events;

use DateTimeImmutable;

final readonly class StepExecuted
{
    /**
     * @param  array<array-key, mixed>  $outputs
     */
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public int $statusCode,
        public array $outputs,
        public bool $criteriaMet,
        public DateTimeImmutable $at,
    ) {}
}
