<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts;

final readonly class PendingCorrelation
{
    public function __construct(
        public string $correlationId,
        public string $executionId,
        public string $stepId,
        public string $channelPath,
        public ?\DateTimeImmutable $expiresAt = null,
    ) {}
}
