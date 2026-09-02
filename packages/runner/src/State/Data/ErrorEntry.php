<?php

declare(strict_types=1);

namespace Alama\Arazzo\State\Data;

final readonly class ErrorEntry
{
    public function __construct(
        public string $type,
        public string $stepId,
        public int $attempts,
        public string $message = '',
        public ?\DateTimeImmutable $timestamp = null,
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
