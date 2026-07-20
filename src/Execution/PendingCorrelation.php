<?php

namespace Alama\LaravelArazzo\Execution;

final readonly class PendingCorrelation
{
    public function __construct(
        public string $correlationId,
        public string $executionId,
        public string $stepId,
        public string $channelPath,
    ) {
    }
}
