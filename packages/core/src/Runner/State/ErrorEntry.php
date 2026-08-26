<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\State;

final class ErrorEntry
{
    public function __construct(
        public readonly string $type,
        public readonly string $stepId,
        public readonly int $attempts,
        public readonly string $message = '',
        public readonly ?\DateTimeImmutable $timestamp = null,
    ) {
        // Default timestamp is handled by getTimestamp()
    }

    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp ?? new \DateTimeImmutable();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'stepId' => $this->stepId,
            'attempts' => $this->attempts,
            'message' => $this->message,
            'timestamp' => $this->getTimestamp()->format(\DATE_ATOM),
        ];
    }
}
