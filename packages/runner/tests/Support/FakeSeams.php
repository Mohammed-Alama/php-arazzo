<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Support;

use Alama\Arazzo\Contracts\Interfaces\OutputExtractorInterface;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\OpenApiPayload;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Engine\Exceptions\ExecutionException;
use Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Fake HTTP/OpenAPI executor for runner tests that don't want to pull in protocol-http.
 */
final class FakeOpenApiExecutor implements OpenApiExecutorInterface
{
    public function __construct(
        private ?ResponseInterface $response = null,
        private mixed $requestInterceptor = null,
    ) {}

    public function execute(
        $operation,
        OpenApiPayload $payload,
        ?callable $requestInterceptor = null,
        ?float $timeoutSeconds = null,
    ): ResponseInterface {
        if ($this->requestInterceptor !== null) {
            $interceptor = $requestInterceptor ?? $this->requestInterceptor ?? static fn ($r) => $r;
            $request = $interceptor($interceptor);
        }

        return $this->response ?? throw new ExecutionException('FakeOpenApiExecutor: no response configured');
    }

    public function withResponse(ResponseInterface $response): self
    {
        $clone = clone $this;
        $clone->response = $response;

        return $clone;
    }
}

/**
 * Fake output extractor for runner tests.
 */
final class FakeOutputExtractor implements OutputExtractorInterface
{
    public function __construct(
        private array $outputs = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function extractOutputs(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): array
    {
        return $this->outputs;
    }

    public function withOutputs(array $outputs): self
    {
        $clone = clone $this;
        $clone->outputs = $outputs;

        return $clone;
    }
}

/**
 * Fake response validator for runner tests.
 */
final class FakeResponseValidator implements ResponseValidatorInterface
{
    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        // No-op for tests
    }
}

/**
 * Creates a minimal set of fake seams for runner tests.
 *
 * @return array{openApiExecutor: FakeOpenApiExecutor, outputExtractor: FakeOutputExtractor, schemaValidator: FakeResponseValidator}
 */
function createFakeSeams(): array
{
    return [
        'openApiExecutor' => new FakeOpenApiExecutor(),
        'outputExtractor' => new FakeOutputExtractor(),
        'schemaValidator' => new FakeResponseValidator(),
    ];
}
