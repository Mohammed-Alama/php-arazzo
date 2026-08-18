<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

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
