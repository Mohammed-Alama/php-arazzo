<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Events;

use DateTimeImmutable;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
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
