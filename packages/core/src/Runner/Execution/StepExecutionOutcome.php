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
     * @param array<string, string> $responseHeaders
     */
    private function __construct(
        public bool $suspended,
        public ?int $statusCode = null,
        public array $outputs = [],
        public array $responseBody = [],
        public array $inputs = [],
        public ?array $request = null,
        public array $responseHeaders = [],
        public ?string $rawBody = null,
        public ?string $contentType = null,
        public ?string $failureCategory = null,
    ) {
    }

    /**
     * @param array<string, mixed> $outputs
     * @param array<string, mixed> $responseBody
     * @param array<string, mixed> $inputs
     * @param array<string, mixed>|null $request
     * @param array<string, string> $responseHeaders
     */
    public static function resolved(int $statusCode, array $outputs, array $responseBody, array $inputs = [], ?array $request = null, array $responseHeaders = [], ?string $rawBody = null, ?string $contentType = null, ?string $failureCategory = null): self
    {
        return new self(false, $statusCode, $outputs, $responseBody, $inputs, $request, $responseHeaders, $rawBody, $contentType, $failureCategory);
    }

    public static function suspended(): self
    {
        return new self(true);
    }
}
