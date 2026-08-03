<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Events;

final readonly class RunStarted
{
    /**
     * @param array<string, mixed> $inputs
     */
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $definitionId,
        public array $inputs,
        public \DateTimeImmutable $at,
    ) {
    }
}
