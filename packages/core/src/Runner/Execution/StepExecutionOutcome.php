<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

final readonly class StepExecutionOutcome
{
    /**
     * @param array<string, mixed> $outputs
     * @param array<string, mixed> $responseBody
     * @param array<string, mixed> $inputs
     * @param array<string, mixed>|null $request
     */
    private function __construct(
        public bool $suspended,
        public ?int $statusCode = null,
        public array $outputs = [],
        public array $responseBody = [],
        public array $inputs = [],
        public ?array $request = null,
    ) {
    }

    /**
     * @param array<string, mixed> $outputs
     * @param array<string, mixed> $responseBody
     * @param array<string, mixed> $inputs
     * @param array<string, mixed>|null $request
     */
    public static function resolved(int $statusCode, array $outputs, array $responseBody, array $inputs = [], ?array $request = null): self
    {
        return new self(false, $statusCode, $outputs, $responseBody, $inputs, $request);
    }

    public static function suspended(): self
    {
        return new self(true);
    }
}
