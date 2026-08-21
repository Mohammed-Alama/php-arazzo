<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

final readonly class StepExecutionOutcome
{
    /**
     * @param array<string, mixed> $outputs
     * @param array<string, mixed> $responseBody
     */
    private function __construct(
        public bool $suspended,
        public ?int $statusCode = null,
        public array $outputs = [],
        public array $responseBody = [],
    ) {
    }

    /**
     * @param array<string, mixed> $outputs
     * @param array<string, mixed> $responseBody
     */
    public static function resolved(int $statusCode, array $outputs, array $responseBody): self
    {
        return new self(false, $statusCode, $outputs, $responseBody);
    }

    public static function suspended(): self
    {
        return new self(true);
    }
}
