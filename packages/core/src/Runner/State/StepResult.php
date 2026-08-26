<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\State;

use Alama\Arazzo\Spec\Enum\StepStatus;

final class StepResult
{
    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $outputs
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $responseHeaders
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly array $request = [],
        public readonly array $response = [],
        public readonly array $outputs = [],
        public readonly array $inputs = [],
        public readonly int $attempts = 0,
        public readonly ?StepStatus $status = null,
        public readonly ?string $failureCategory = null,
        public readonly string $contentType = 'application/json',
        public readonly string $responseBody = '',
        public readonly string $rawBody = '',
        public readonly array $responseHeaders = [],
    ) {}

    /**
     * @param  array<string, mixed>  $outputs
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $responseHeaders
     * @param  array<string, mixed>  $request
     */
    public static function success(
        int $statusCode,
        array $outputs,
        array $inputs,
        string $contentType = 'application/json',
        string $responseBody = '',
        string $rawBody = '',
        array $responseHeaders = [],
        array $request = [],
        int $attempts = 0,
    ): self {
        return new self(
            statusCode: $statusCode,
            request: $request,
            response: ['statusCode' => $statusCode, 'headers' => $responseHeaders, 'body' => $responseBody],
            outputs: $outputs,
            inputs: $inputs,
            attempts: $attempts,
            status: StepStatus::Succeeded,
            contentType: $contentType,
            responseBody: $responseBody,
            rawBody: $rawBody,
            responseHeaders: $responseHeaders,
        );
    }

    /**
     * @param  array<string, mixed>  $outputs
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $responseHeaders
     * @param  array<string, mixed>  $request
     */
    public static function failure(
        int $statusCode,
        string $failureCategory,
        array $outputs,
        array $inputs,
        string $contentType = 'application/json',
        string $responseBody = '',
        string $rawBody = '',
        array $responseHeaders = [],
        array $request = [],
        int $attempts = 0,
    ): self {
        return new self(
            statusCode: $statusCode,
            request: $request,
            response: ['statusCode' => $statusCode, 'headers' => $responseHeaders, 'body' => $responseBody],
            outputs: $outputs,
            inputs: $inputs,
            attempts: $attempts,
            status: StepStatus::Failed,
            failureCategory: $failureCategory,
            contentType: $contentType,
            responseBody: $responseBody,
            rawBody: $rawBody,
            responseHeaders: $responseHeaders,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'statusCode' => $this->statusCode,
            'request' => $this->request,
            'response' => $this->response,
            'outputs' => $this->outputs,
            'inputs' => $this->inputs,
            'attempts' => $this->attempts,
            'status' => $this->status?->value,
            'failureCategory' => $this->failureCategory,
            'contentType' => $this->contentType,
            'responseBody' => $this->responseBody,
            'rawBody' => $this->rawBody,
            'responseHeaders' => $this->responseHeaders,
        ];
    }
}
