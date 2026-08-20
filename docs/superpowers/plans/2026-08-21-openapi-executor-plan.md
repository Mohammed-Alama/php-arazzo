# OpenAPI Executor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Decouple HTTP dispatching from `StepExecutor` by introducing a dedicated `OpenApiExecutor` that builds and sends PSR-7 requests independently of Arazzo models.

**Architecture:** We will create `OpenApiPayload` to pass evaluated parameters grouped by location (`path`, `query`, `header`, `auto`). `DefaultOpenApiExecutor` will use the OpenAPI schema to resolve `auto` parameter locations, cast types, build the PSR-7 request, and send it via PSR-18. `StepExecutor` will be refactored to depend on `OpenApiExecutorInterface` and `ArazzoRequestCompiler` will be removed.

**Tech Stack:** PHP 8.4, PSR-7, PSR-18, Pest PHP

---

### Task 1: Create Interfaces and DTOs

**Files:**
- Create: `packages/core/src/Runner/Dto/OpenApiPayload.php`
- Create: `packages/core/src/Runner/Contracts/OpenApiExecutorInterface.php`

- [ ] **Step 1: Create `OpenApiPayload` DTO**

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Dto;

class OpenApiPayload
{
    /**
     * @param array<string, mixed> $path
     * @param array<string, mixed> $query
     * @param array<string, mixed> $header
     * @param array<string, mixed> $auto
     */
    public function __construct(
        public array $path = [],
        public array $query = [],
        public array $header = [],
        public array $auto = [],
        public mixed $body = null,
    ) {
    }
}
```

- [ ] **Step 2: Create `OpenApiExecutorInterface`**

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Contracts;

use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Runner\Dto\OpenApiPayload;
use Psr\Http\Message\ResponseInterface;

interface OpenApiExecutorInterface
{
    public function execute(
        SourceDescription $source,
        string $operationIdOrPath,
        OpenApiPayload $payload
    ): ResponseInterface;
}
```

---

### Task 2: Implement DefaultOpenApiExecutor

**Files:**
- Create: `packages/core/src/Runner/DefaultOpenApiExecutor.php`

- [ ] **Step 1: Scaffold `DefaultOpenApiExecutor`**

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Resolver\SourceResolver;
use Alama\Arazzo\Runner\Contracts\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Dto\OpenApiPayload;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class DefaultOpenApiExecutor implements OpenApiExecutorInterface
{
    public function __construct(
        private SourceResolver $sourceResolver,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private ?LoggerInterface $logger = null,
    ) {
    }
    
    public function execute(
        SourceDescription $source,
        string $operationIdOrPath,
        OpenApiPayload $payload
    ): ResponseInterface {
        // We will move the logic from ArazzoRequestCompiler here.
        // For now, return a dummy response to pass syntax check.
        return $this->requestFactory->createResponse(200);
    }
}
```
*(Note: A `createResponse` method doesn't exist on `RequestFactoryInterface`, you'll need `ResponseFactoryInterface` or just use Guzzle's `Response` class for the stub, but we will fully implement it in the next step).*

- [ ] **Step 2: Port OpenAPI parsing and request building logic from `ArazzoRequestCompiler`**

Move the logic that finds the OpenAPI document, locates the operation, and resolves parameters. Update it to use `$payload->auto` to determine the parameter location using the OpenAPI schema, and merge them into `$payload->path`, `$payload->query`, and `$payload->header`. Cast values based on schema. Finally, build the PSR-7 request and use `$this->httpClient->sendRequest($request)`.

*(The implementer will need to adapt the extensive OpenAPI parsing code previously in `ArazzoRequestCompiler` to read from the new `OpenApiPayload`).*

- [ ] **Step 3: Test `DefaultOpenApiExecutor`**
Write a Pest test in `packages/core/tests/Runner/DefaultOpenApiExecutorTest.php` that verifies parameter routing and HTTP dispatching works.

---

### Task 3: Refactor StepExecutor

**Files:**
- Modify: `packages/core/src/Runner/StepExecutor.php`

- [ ] **Step 1: Replace Dependencies**

Replace `ClientInterface` and `ExpressionResolverInterface::compileRequest` usage with `OpenApiExecutorInterface`.

```php
    public function __construct(
        private OpenApiExecutorInterface $openApiExecutor,
        private ExpressionResolverInterface $expressionResolver,
        // ... existing dependencies
    ) {
```

- [ ] **Step 2: Update `execute` method to build `OpenApiPayload`**

```php
    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document): array
    {
        $payload = new \Alama\Arazzo\Runner\Dto\OpenApiPayload();
        
        foreach ($step->parameters as $param) {
            $val = $param->value instanceof \Alama\Arazzo\Dto\Expression
                ? $this->expressionResolver->evaluate($param->value, $context, $step->stepId)
                : $param->value;
                
            $in = $param->in?->value ?? 'auto';
            if ($in === 'query') $payload->query[$param->name] = $val;
            elseif ($in === 'header') $payload->header[$param->name] = $val;
            elseif ($in === 'path') $payload->path[$param->name] = $val;
            else $payload->auto[$param->name] = $val;
        }

        // Evaluate requestBody replacements
        $bodyData = [];
        if ($step->requestBody && $step->requestBody->payload !== null) {
            $bodyData = $step->requestBody->payload;
            if ($step->requestBody->replacements) {
                foreach ($step->requestBody->replacements as $replacement) {
                    $targetPtr = $replacement->target;
                    $val = $replacement->value instanceof \Alama\Arazzo\Dto\Expression
                        ? $this->expressionResolver->evaluate($replacement->value, $context, $step->stepId)
                        : $replacement->value;
                    
                    // Apply JSON pointer replacement logic here
                    // ... (implementer to port json pointer logic)
                }
            }
        }
        $payload->body = empty($bodyData) ? null : $bodyData;
        
        // Find source description
        $sourceDesc = $document->sourceDescriptions[0] ?? throw new \RuntimeException("No SourceDescription found");
        
        $operation = $step->operationId ?? $step->operationPath ?? '/';

        // 2. Send HTTP Request
        try {
            $response = $this->openApiExecutor->execute($sourceDesc, $operation, $payload);
            
            // ... (keep existing schema validation and context updating logic)
```

- [ ] **Step 3: Fix tests for `StepExecutor`**
Update `packages/core/tests/Runner/StepExecutorTest.php` to mock `OpenApiExecutorInterface` instead of `ClientInterface`.

---

### Task 4: Cleanup

**Files:**
- Delete: `packages/core/src/Runner/ArazzoRequestCompiler.php`
- Delete: `packages/core/src/Runner/Contracts/RequestCompilerInterface.php`
- Modify: `packages/core/src/Runner/ArazzoExpressionResolver.php`

- [ ] **Step 1: Remove RequestCompiler**
Delete the deprecated RequestCompiler interface and its implementation, as this logic now lives in `DefaultOpenApiExecutor`.

- [ ] **Step 2: Remove from ArazzoExpressionResolver**
Remove `RequestCompilerInterface` from `ArazzoExpressionResolver` constructor and throw an exception if `compileRequest` is called, or remove the method entirely if no longer required by the interface.

- [ ] **Step 3: Run all tests**
Run `composer test` to ensure everything is green.
