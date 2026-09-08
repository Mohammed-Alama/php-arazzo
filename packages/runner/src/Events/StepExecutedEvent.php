<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Events;

use DateTimeImmutable;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class StepExecutedEvent
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
