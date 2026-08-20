# Design Specification: OpenAPI Executor Decoupling

## 1. Overview
Currently, the `StepExecutor` in `php-arazzo` handles both Arazzo expression evaluation and PSR-18 HTTP Request dispatching (via `ArazzoRequestCompiler`). This violates separation of concerns and prevents developers from programmatically executing OpenAPI operations without wrapping them in an Arazzo workflow context.

This design decouples the HTTP dispatching into a dedicated, reusable `OpenApiExecutor`. The Arazzo `StepExecutor` will handle expression resolution and hand off a clean DTO (`OpenApiPayload`) to the new executor.

## 2. Interfaces & DTOs
We introduce a lightweight DTO to pass evaluated parameters grouped by their target location, respecting Arazzo's ability to override OpenAPI defaults.

```php
namespace Alama\Arazzo\Runner\Dto;

class OpenApiPayload {
    public function __construct(
        public array $path = [],
        public array $query = [],
        public array $header = [],
        public array $auto = [], // Parameters omitted 'in' property; OpenAPI decides
        public mixed $body = null,
    ) {}
}
```

The executor interface isolates the runner from HTTP client specifics:

```php
namespace Alama\Arazzo\Runner\Contracts;

use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Runner\Dto\OpenApiPayload;
use Psr\Http\Message\ResponseInterface;

interface OpenApiExecutorInterface {
    public function execute(
        SourceDescription $source,
        string $operationIdOrPath,
        OpenApiPayload $payload
    ): ResponseInterface;
}
```

## 3. The OpenAPI Executor Implementation
A new class `Alama\Arazzo\Runner\DefaultOpenApiExecutor` will be created. 
It receives the PSR-18 `ClientInterface` and PSR-17 `RequestFactoryInterface`.

**Responsibilities:**
1. Parse the OpenAPI document from the `SourceDescription`.
2. Locate the target operation using `$operationIdOrPath`.
3. Resolve `auto` parameters: Match them against the OpenAPI schema to determine if they belong in the path, query, or header.
4. Type-cast parameters (e.g. converting a string `"5"` to an integer `5`) as defined by the OpenAPI schema.
5. Construct the PSR-7 Request, send it via the PSR-18 Client, and return the resulting PSR-7 Response.

## 4. Refactoring `StepExecutor`
`StepExecutor` will drop the PSR-18 `ClientInterface` dependency and instead inject `OpenApiExecutorInterface`.

**Execution Flow:**
1. Evaluate `$step->parameters` using the `ExpressionEvaluatorInterface`.
2. Group evaluated parameters into the `OpenApiPayload` buckets based on their Arazzo `in` property.
3. Evaluate `$step->requestBody` replacements and assign to `$payload->body`.
4. Call `$this->openApiExecutor->execute($document->sourceDescriptions[0], $step->operationId ?? $step->operationPath, $payload)`.
5. Process the returned `ResponseInterface` (extract headers/body, perform schema validation, and extract Arazzo outputs).

## 5. Cleanup
The existing `RequestCompilerInterface` and `ArazzoRequestCompiler` will be removed or gutted, as their OpenAPI resolution logic is fully absorbed by the `DefaultOpenApiExecutor`.
