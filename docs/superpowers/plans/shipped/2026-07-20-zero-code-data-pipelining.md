# Zero-Code Data Pipelining Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish `ArazzoExpressionResolver`/`TypeCaster`/`JsonPathEvaluator` into the one real implementation of `ExpressionResolverInterface`, and make the synchronous `StepExecutor` use it instead of its own inline duplicate logic — retiring `VariableContext`/`ConditionEvaluator` in favor of the immutable `WorkflowContext` already used by the (still-unwired) async path.

**Architecture:** `ArazzoExpressionResolver` becomes the single place that resolves OpenAPI operations, builds requests, extracts outputs (spec runtime expressions or JSONPath), and evaluates success criteria (simple/regex/jsonpath). `StepExecutor` becomes a thin orchestrator around it (compile → send → record → extract → evaluate), threading an immutable `WorkflowContext` through each step by reassignment rather than mutation.

**Tech Stack:** PHP 8.4, PSR-7/17/18 HTTP interfaces, `cebe/php-openapi` for OpenAPI schema access, `guzzlehttp/psr7` for PSR-7 implementations, Pest/PHPUnit (`vendor/bin/pest`) for tests.

> **Addendum (2026-07-20) — Arazzo 1.1.0 confirmed real, before this plan was executed (0/66
> steps checked):** this plan is still correctly scoped to 1.0.0 parity — execute it as
> written. Two things to know before starting so you don't build throwaway work:
> - **Task 8** hardcodes `CriterionType::XPath => throw UnsupportedCriterionTypeException`.
>   That's still the right call for *this* plan, but the underlying reason ("no XML use case")
>   is now false — 1.1.0's Selector Object needs real `xpath` support. Don't extend the
>   exception message or add a TODO implying it'll never be needed; it will, in a follow-up.
> - **Task 7**'s bare-`$.`-prefix JSONPath sniffing in `extractOutputs` is a 1.0.0-appropriate
>   convenience heuristic. It will be superseded (not extended) by a real Selector Object
>   (`context`/`selector`/pinned-version `type`) in the 1.1.0 follow-up — don't add more
>   prefix-detection special cases on top of it later; replace the mechanism instead.
>
> See `docs/superpowers/specs/2026-07-20-zero-code-data-pipelining-design.md`'s addendum and
> `docs/superpowers/roadmap/ROADMAP.md`'s "Arazzo 1.1.0" section for the full delta.

## Global Constraints

- No throwing changes to `TypeCaster`'s existing cast methods — `asInteger`/`asString`/`asArray` keep throwing `InvalidArgumentException` on bad input; best-effort fallback (catch + log + keep raw value) lives in the resolver, not in `TypeCaster`.
- `CriterionType::XPath` throws `UnsupportedCriterionTypeException` — no XML support is added.
- `ExpressionResolverInterface`'s `ArazzoDocument $document` parameters are optional (`?ArazzoDocument $document = null`) — the async `StepExecutionWorker` path has no document available yet (deferred to roadmap item 03) and must keep compiling and passing its existing tests unmodified in behavior.
- Every file this plan touches must pass `vendor/bin/pest` and `vendor/bin/phpstan analyse` before being considered done.

---

## Task 1: `WorkflowContext` — immutable step-request/response/output mutators

**Files:**
- Modify: `src/Execution/WorkflowContext.php`
- Test: `tests/Unit/Execution/WorkflowContextTest.php`

**Interfaces:**
- Produces: `WorkflowContext::withStepRequest(string $stepId, array $request): self`, `WorkflowContext::withStepResponse(string $stepId, array $response): self`, `WorkflowContext::withStepOutput(string $stepId, string $key, mixed $value): self` — all immutable, all merge into the existing `steps[$stepId] = ['request' => …, 'response' => …, 'outputs' => …]` shape that `ExpressionEvaluator` already reads (Task 3 consumes this).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Unit/Execution/WorkflowContextTest.php` (keep the existing `test_immutability` method, add these):

```php
    public function test_with_step_request_is_immutable_and_merges_into_steps(): void
    {
        $context = new WorkflowContext('def_1');
        $newContext = $context->withStepRequest('step_1', ['method' => 'GET', 'url' => 'http://x']);

        $this->assertNotSame($context, $newContext);
        $this->assertEmpty($context->getSteps());
        $this->assertEquals(['method' => 'GET', 'url' => 'http://x'], $newContext->getSteps()['step_1']['request']);
    }

    public function test_with_step_response_merges_alongside_existing_request(): void
    {
        $context = (new WorkflowContext('def_1'))
            ->withStepRequest('step_1', ['method' => 'GET'])
            ->withStepResponse('step_1', ['statusCode' => 200]);

        $this->assertEquals(['method' => 'GET'], $context->getSteps()['step_1']['request']);
        $this->assertEquals(['statusCode' => 200], $context->getSteps()['step_1']['response']);
    }

    public function test_with_step_output_merges_individual_keys(): void
    {
        $context = (new WorkflowContext('def_1'))
            ->withStepOutput('step_1', 'id', 123)
            ->withStepOutput('step_1', 'name', 'Alice');

        $this->assertEquals(['id' => 123, 'name' => 'Alice'], $context->getSteps()['step_1']['outputs']);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Execution/WorkflowContextTest.php`
Expected: FAIL with "Call to undefined method ...WorkflowContext::withStepRequest()"

- [ ] **Step 3: Implement the mutators**

Replace the full contents of `src/Execution/WorkflowContext.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

final class WorkflowContext
{
    public function __construct(
        private string $definitionId,
        private array $inputs = [],
        private array $steps = [],
        private array $components = [],
    ) {
    }

    public function getDefinitionId(): string
    {
        return $this->definitionId;
    }

    public function getInputs(): array
    {
        return $this->inputs;
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function getComponents(): array
    {
        return $this->components;
    }

    public function withStepResult(string $stepId, array $result): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId] = $result;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components);
    }

    public function withStepRequest(string $stepId, array $request): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['request'] = $request;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components);
    }

    public function withStepResponse(string $stepId, array $response): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['response'] = $response;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components);
    }

    public function withStepOutput(string $stepId, string $key, mixed $value): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['outputs'][$key] = $value;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Execution/WorkflowContextTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Execution/WorkflowContext.php tests/Unit/Execution/WorkflowContextTest.php
git commit -m "feat: add immutable step request/response/output mutators to WorkflowContext"
```

---

## Task 2: `TypeCaster` — add `asFloat`/`asBoolean`

**Files:**
- Modify: `src/Execution/TypeCaster.php`
- Test: `tests/Unit/Execution/TypeCasterTest.php`

**Interfaces:**
- Produces: `TypeCaster::asFloat(mixed $value): float` (throws `InvalidArgumentException` on failure), `TypeCaster::asBoolean(mixed $value): bool` (throws `InvalidArgumentException` on failure). Both consumed by `ArazzoExpressionResolver::castToSchemaType()` in Task 6.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Unit/Execution/TypeCasterTest.php` (keep existing tests, add these):

```php
    public function test_casts_to_float(): void
    {
        $this->assertSame(4.2, TypeCaster::asFloat('4.2'));
        $this->assertSame(42.0, TypeCaster::asFloat(42));
    }

    public function test_throws_on_invalid_float(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TypeCaster::asFloat('not-a-number');
    }

    public function test_casts_to_boolean(): void
    {
        $this->assertTrue(TypeCaster::asBoolean(true));
        $this->assertTrue(TypeCaster::asBoolean('true'));
        $this->assertFalse(TypeCaster::asBoolean('false'));
        $this->assertTrue(TypeCaster::asBoolean(1));
        $this->assertFalse(TypeCaster::asBoolean(0));
    }

    public function test_throws_on_invalid_boolean(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TypeCaster::asBoolean('not-a-bool');
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Execution/TypeCasterTest.php`
Expected: FAIL with "Call to undefined method ...TypeCaster::asFloat()"

- [ ] **Step 3: Implement the new cast methods**

Replace the full contents of `src/Execution/TypeCaster.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution;

class TypeCaster
{
    public static function asInteger(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int)$value;
        }
        throw new \InvalidArgumentException("Cannot cast to integer.");
    }

    public static function asFloat(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        throw new \InvalidArgumentException("Cannot cast to float.");
    }

    public static function asBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value) && in_array(strtolower($value), ['true', 'false'], true)) {
            return strtolower($value) === 'true';
        }
        if (is_numeric($value)) {
            return (bool) $value;
        }
        throw new \InvalidArgumentException("Cannot cast to boolean.");
    }

    public static function asString(mixed $value): string
    {
        if (is_scalar($value)) {
            return is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
        }
        throw new \InvalidArgumentException("Cannot cast to string.");
    }

    public static function asArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        return [$value];
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Execution/TypeCasterTest.php`
Expected: PASS (8 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Execution/TypeCaster.php tests/Unit/Execution/TypeCasterTest.php
git commit -m "feat: add asFloat/asBoolean to TypeCaster"
```

---

## Task 3: `ExpressionEvaluator` — retype to `WorkflowContext`, add `HttpMetaRef`

**Files:**
- Modify: `src/Execution/ExpressionEvaluator.php`
- Modify: `tests/Execution/ExpressionEvaluatorTest.php`

**Interfaces:**
- Consumes: `WorkflowContext::getSteps()`/`getInputs()`/`getComponents()` (Task 1, unchanged read shape).
- Produces: `ExpressionEvaluator::evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed`. The new optional `$currentStepId` is used only to resolve bare `HttpMetaRef` nodes (`{$statusCode}`, `{$method}`, `{$url}`) against "the step currently being evaluated" — when null, `HttpMetaRef` resolves to `null` (same degrade-gracefully behavior as today's silent gap). Consumed by `ArazzoExpressionResolver` (Tasks 6-8) and `StepExecutor` is not a direct caller (it goes through the resolver).

- [ ] **Step 1: Write the failing tests**

Replace the full contents of `tests/Execution/ExpressionEvaluatorTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Execution;

use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\WorkflowContext;

it('evaluates input references', function () {
    $context = new WorkflowContext('def_1', ['userId' => 123]);
    $evaluator = new ExpressionEvaluator();

    $expr = new Expression('{$inputs.userId}');
    expect($evaluator->evaluate($expr, $context))->toBe(123);

    $exprNotFound = new Expression('{$inputs.missing}');
    expect($evaluator->evaluate($exprNotFound, $context))->toBeNull();
});

it('evaluates step output references', function () {
    $context = (new WorkflowContext('def_1'))->withStepOutput('create-user', 'id', 456);
    $evaluator = new ExpressionEvaluator();

    $expr = new Expression('{$steps.create-user.outputs.id}');
    expect($evaluator->evaluate($expr, $context))->toBe(456);
});

it('evaluates request parts using json pointer', function () {
    $context = (new WorkflowContext('def_1'))->withStepRequest('step1', [
        'headers' => ['Authorization' => 'Bearer token'],
        'query' => ['search' => 'test'],
        'path' => ['id' => 789],
        'body' => ['user' => ['name' => 'Alice']],
    ]);

    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$steps.step1.request.header.Authorization}'), $context))->toBe('Bearer token');
    expect($evaluator->evaluate(new Expression('{$steps.step1.request.body#/user/name}'), $context))->toBe('Alice');
});

it('evaluates response parts using json pointer', function () {
    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', [
        'statusCode' => 201,
        'headers' => ['X-RateLimit' => '100'],
        'body' => ['data' => ['items' => [1, 2, 3]]],
    ]);

    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$steps.step1.response.statusCode}'), $context))->toBe(201);
    expect($evaluator->evaluate(new Expression('{$steps.step1.response.header.X-RateLimit}'), $context))->toBe('100');
    expect($evaluator->evaluate(new Expression('{$steps.step1.response.body#/data/items/1}'), $context))->toBe(2);
    expect($evaluator->evaluate(new Expression('{$steps.step1.response.body#/missing}'), $context))->toBeNull();
});

it('evaluates json pointer with escaped characters', function () {
    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', [
        'body' => [
            'foo~bar' => 'tilde',
            'foo/bar' => 'slash',
        ],
    ]);

    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$steps.step1.response.body#/foo~0bar}'), $context))->toBe('tilde');
    expect($evaluator->evaluate(new Expression('{$steps.step1.response.body#/foo~1bar}'), $context))->toBe('slash');
});

it('evaluates bare HttpMetaRef against the current step when stepId is given', function () {
    $context = (new WorkflowContext('def_1'))
        ->withStepRequest('step1', ['method' => 'POST', 'url' => 'http://x/y'])
        ->withStepResponse('step1', ['statusCode' => 201]);

    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$statusCode}'), $context, 'step1'))->toBe(201);
    expect($evaluator->evaluate(new Expression('{$method}'), $context, 'step1'))->toBe('POST');
    expect($evaluator->evaluate(new Expression('{$url}'), $context, 'step1'))->toBe('http://x/y');
});

it('returns null for HttpMetaRef when no current step is given', function () {
    $context = new WorkflowContext('def_1');
    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$statusCode}'), $context))->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/ExpressionEvaluatorTest.php`
Expected: FAIL — `ExpressionEvaluator::evaluate()` currently type-hints `VariableContext`, so passing `WorkflowContext` is a `TypeError`; the `HttpMetaRef` tests fail because that AST case isn't handled.

- [ ] **Step 3: Implement the retype and `HttpMetaRef` case**

Replace the full contents of `src/Execution/ExpressionEvaluator.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Expression\Ast\ComponentRef;
use Alama\LaravelArazzo\Expression\Ast\ExpressionAst;
use Alama\LaravelArazzo\Expression\Ast\HttpMetaRef;
use Alama\LaravelArazzo\Expression\Ast\InputRef;
use Alama\LaravelArazzo\Expression\Ast\OutputPart;
use Alama\LaravelArazzo\Expression\Ast\RequestPart;
use Alama\LaravelArazzo\Expression\Ast\ResponsePart;
use Alama\LaravelArazzo\Expression\Ast\StepRef;

class ExpressionEvaluator
{
    public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed
    {
        $ast = $expression->ast();

        return $this->evaluateAst($ast, $context, $currentStepId);
    }

    private function evaluateAst(ExpressionAst $ast, WorkflowContext $context, ?string $currentStepId): mixed
    {
        if ($ast instanceof InputRef) {
            return $context->getInputs()[$ast->name] ?? null;
        }

        if ($ast instanceof HttpMetaRef) {
            if ($currentStepId === null) {
                return null;
            }

            $stepData = $context->getSteps()[$currentStepId] ?? null;
            if (!$stepData) {
                return null;
            }

            return match ($ast->field) {
                'statusCode' => $stepData['response']['statusCode'] ?? null,
                'method' => $stepData['request']['method'] ?? null,
                'url' => $stepData['request']['url'] ?? null,
            };
        }

        if ($ast instanceof StepRef) {
            $steps = $context->getSteps();
            $stepData = $steps[$ast->stepId] ?? null;
            if (!$stepData) {
                return null;
            }

            $part = $ast->part;

            if ($part instanceof RequestPart) {
                $req = $stepData['request'] ?? [];
                $target = match ($part->httpPart) {
                    'header' => $req['headers'][$part->headerName] ?? null,
                    'body' => JsonPointer::resolve($req['body'] ?? [], $part->jsonPointer),
                    default => null,
                };

                return $target;
            }

            if ($part instanceof ResponsePart) {
                $res = $stepData['response'] ?? [];
                if ($part->httpPart === 'statusCode') {
                    return $res['statusCode'] ?? null;
                }

                $target = match ($part->httpPart) {
                    'header' => $res['headers'][$part->headerName] ?? null,
                    'body' => JsonPointer::resolve($res['body'] ?? [], $part->jsonPointer),
                    default => null,
                };

                return $target;
            }

            if ($part instanceof OutputPart) {
                return $stepData['outputs'][$part->name] ?? null;
            }
        }

        if ($ast instanceof ComponentRef) {
            $comps = $context->getComponents();
            if ($ast->type === 'parameters') {
                return $comps['parameters'][$ast->name] ?? null;
            }
        }

        return null;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/ExpressionEvaluatorTest.php`
Expected: PASS (7 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Execution/ExpressionEvaluator.php tests/Execution/ExpressionEvaluatorTest.php
git commit -m "feat: retype ExpressionEvaluator to WorkflowContext, add HttpMetaRef support"
```

---

## Task 4: `OpenApiParser` — also return the resolved `Operation`

**Files:**
- Modify: `src/Execution/OpenApiParser.php`
- Test: Create `tests/Unit/Execution/OpenApiParserTest.php`

**Interfaces:**
- Produces: `OpenApiParser::findOperation(OpenApi $openApi, string $operationId): array{0: string, 1: string, 2: \cebe\openapi\spec\Operation}` — third tuple element is new. Consumed by `ArazzoExpressionResolver` (Tasks 6-7) to read parameter/response schemas.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Execution/OpenApiParserTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Execution\OpenApiParser;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\OpenApi;
use PHPUnit\Framework\TestCase;

class OpenApiParserTest extends TestCase
{
    public function test_finds_method_path_and_operation_by_operation_id(): void
    {
        $openApi = new OpenApi([
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test', 'version' => '1.0'],
            'paths' => [
                '/pets/{id}' => [
                    'get' => ['operationId' => 'getPet', 'responses' => []],
                ],
            ],
        ]);

        [$method, $path, $operation] = OpenApiParser::findOperation($openApi, 'getPet');

        $this->assertSame('GET', $method);
        $this->assertSame('/pets/{id}', $path);
        $this->assertInstanceOf(Operation::class, $operation);
        $this->assertSame('getPet', $operation->operationId);
    }

    public function test_throws_when_operation_not_found(): void
    {
        $openApi = new OpenApi([
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test', 'version' => '1.0'],
            'paths' => [],
        ]);

        $this->expectException(\RuntimeException::class);
        OpenApiParser::findOperation($openApi, 'missingOp');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Execution/OpenApiParserTest.php`
Expected: FAIL — `$operation` is undefined (current `findOperation` returns a 2-element array).

- [ ] **Step 3: Implement the change**

In `src/Execution/OpenApiParser.php`, replace the `findOperation` method body:

```php
    /**
     * @return array{0: string, 1: string, 2: Operation}
     */
    public static function findOperation(OpenApi $openApi, string $operationId): array
    {
        foreach ($openApi->paths as $path => $pathItem) {
            /** @var PathItem $pathItem */
            $methods = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];
            foreach ($methods as $method) {
                /** @var Operation|null $operation */
                $operation = $pathItem->$method;
                if ($operation !== null && $operation->operationId === $operationId) {
                    return [strtoupper($method), (string) $path, $operation];
                }
            }
        }

        throw new RuntimeException("Operation '{$operationId}' not found in OpenAPI specification.");
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Execution/OpenApiParserTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Execution/OpenApiParser.php tests/Unit/Execution/OpenApiParserTest.php
git commit -m "feat: return resolved Operation from OpenApiParser::findOperation"
```

---

## Task 5: `ExpressionResolverInterface` — extend signature, add exception

**Files:**
- Modify: `src/Execution/Contracts/ExpressionResolverInterface.php`
- Create: `src/Execution/Exceptions/UnsupportedCriterionTypeException.php`

**Interfaces:**
- Produces: the new interface contract every implementer (Task 6-8's `ArazzoExpressionResolver`, and the test double in Task 11) must satisfy:
  ```php
  compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
  extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
  evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
  ```
  and `UnsupportedCriterionTypeException` (thrown by `evaluateSuccessCriteria` for `CriterionType::XPath`).

This task has no independent runtime behavior to test — it's a pure signature/type change whose correctness is verified by Tasks 6-8 (implementers) and Task 11 (the test double) compiling and passing. No separate test step.

- [ ] **Step 1: Update the interface**

Replace the full contents of `src/Execution/Contracts/ExpressionResolverInterface.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution\Contracts;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Psr\Http\Message\RequestInterface;

interface ExpressionResolverInterface
{
    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface;

    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array;

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool;
}
```

- [ ] **Step 2: Create the exception**

Create `src/Execution/Exceptions/UnsupportedCriterionTypeException.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Exceptions;

use RuntimeException;

final class UnsupportedCriterionTypeException extends RuntimeException
{
}
```

- [ ] **Step 3: Confirm it doesn't compile yet (expected, fixed by later tasks)**

Run: `vendor/bin/phpstan analyse src/Execution/Contracts/ExpressionResolverInterface.php`
Expected: passes on its own (the interface itself is self-consistent); `ArazzoExpressionResolver` and the `StepExecutionMockExpressionResolver` test double will fail to satisfy it until Tasks 6-8 and 12 land — that's expected and resolved by the end of this plan, not this task.

- [ ] **Step 4: Commit**

```bash
git add src/Execution/Contracts/ExpressionResolverInterface.php src/Execution/Exceptions/UnsupportedCriterionTypeException.php
git commit -m "feat: extend ExpressionResolverInterface with document param and success-criteria method"
```

---

## Task 6: `ArazzoExpressionResolver::compileRequest` — real OpenAPI-driven request building

**Files:**
- Modify: `src/Execution/ArazzoExpressionResolver.php`
- Test: `tests/Unit/Execution/ArazzoExpressionResolverTest.php` (full rewrite starts here, continues in Tasks 7-8)

**Interfaces:**
- Consumes: `OpenApiParser::findOperation()` (Task 4), `ExpressionEvaluator::evaluate()` (Task 3), `TypeCaster::asInteger/asFloat/asString/asBoolean/asArray` (Task 2), `SourceResolver::resolve()` (existing), `ExpressionResolverInterface` (Task 5).
- Produces: the schema-lookup/cast private helpers (`findParameterSchema`, `findRequestBodySchema`, `resolveSchemaAtPointer`, `castToSchemaType`, `resolveOpenApiDocument`) that Task 7's `extractOutputs` reuses.

**Pre-existing bug found and fixed here:** the old `StepExecutor` code assumed `$resolvedSource->extract('')` (from `SourceResolver::resolve()`) returns a `cebe\openapi\spec\OpenApi` instance directly, and gated all OpenAPI-driven logic on `instanceof OpenApi`. That's only true for the hand-rolled fake `ResolvedSource` in `WorkflowExecutorTest` — the real `Alama\LaravelArazzo\Resolution\OpenApiResolvedSource::extract('')` returns `$openapi->getSerializableData()`, a plain array/stdClass (see `src/Resolution/OpenApiResolvedSource.php:19-26`), not the typed object. Against the real resolution chain (exactly what Task 13 wires into the service provider), the old `instanceof OpenApi` check was always false, so operation resolution silently never ran in production — an untested, dormant defect. Step 3 below fixes this with a `resolveOpenApiDocument()` helper that falls back to re-parsing serializable data via `cebe\openapi\Reader` when `extract('')` doesn't hand back a typed object.

- [ ] **Step 1: Write the failing tests**

Replace the full contents of `tests/Unit/Execution/ArazzoExpressionResolverTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\PayloadReplacement;
use Alama\LaravelArazzo\Dto\RequestBody;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\ArazzoExpressionResolver;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Resolution\Fetchers\LocalFetcher;
use Alama\LaravelArazzo\Resolution\Parsers\OpenApiSourceParser;
use Alama\LaravelArazzo\Resolution\DefaultSourceResolver;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

class ArazzoExpressionResolverTest extends TestCase
{
    private string $openApiFile;

    protected function setUp(): void
    {
        parent::setUp();

        $openApiJson = json_encode([
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test', 'version' => '1.0'],
            'servers' => [['url' => 'https://api.test']],
            'paths' => [
                '/users' => [
                    'post' => [
                        'operationId' => 'createUser',
                        'parameters' => [
                            ['name' => 'dryRun', 'in' => 'query', 'schema' => ['type' => 'boolean']],
                        ],
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'age' => ['type' => 'integer'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => [
                                'description' => 'Created',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'integer'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->openApiFile = tempnam(sys_get_temp_dir(), 'openapi_') . '.json';
        file_put_contents($this->openApiFile, $openApiJson);
    }

    protected function tearDown(): void
    {
        @unlink($this->openApiFile);
        parent::tearDown();
    }

    private function makeResolver(): ArazzoExpressionResolver
    {
        $sourceResolver = new DefaultSourceResolver(
            fetchers: ['file' => new LocalFetcher()],
            parsers: [SourceType::Openapi->value => new OpenApiSourceParser()],
        );

        return new ArazzoExpressionResolver($sourceResolver, new HttpFactory(), new ExpressionEvaluator());
    }

    private function makeDocument(): ArazzoDocument
    {
        return new ArazzoDocument(
            arazzo: '1.0.1',
            info: new Info('Test', null, null, '1.0.0'),
            sourceDescriptions: [new SourceDescription('test-api', $this->openApiFile, SourceType::Openapi)],
            workflows: [],
            components: new Components([], [], [], []),
            specificationExtensions: [],
        );
    }

    public function test_compiles_request_with_resolved_operation_and_cast_query_param(): void
    {
        $resolver = $this->makeResolver();
        $document = $this->makeDocument();

        $step = new Step(
            stepId: 'create-user',
            description: null,
            operationId: 'createUser',
            operationPath: null,
            workflowId: null,
            parameters: [new Parameter('dryRun', ParameterIn::Query, 'true')],
            requestBody: null,
            successCriteria: [],
            onSuccess: [],
            onFailure: [],
            outputs: [],
        );

        $request = $resolver->compileRequest($step, new WorkflowContext('def_1'), $document);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.test/users?dryRun=1', (string) $request->getUri());
    }

    public function test_compiles_request_body_with_schema_cast_replacement(): void
    {
        $resolver = $this->makeResolver();
        $document = $this->makeDocument();

        $step = new Step(
            stepId: 'create-user',
            description: null,
            operationId: 'createUser',
            operationPath: null,
            workflowId: null,
            parameters: [],
            requestBody: new RequestBody('application/json', ['age' => null], [
                new PayloadReplacement('/age', new Expression('{$inputs.age}')),
            ]),
            successCriteria: [],
            onSuccess: [],
            onFailure: [],
            outputs: [],
        );

        $context = new WorkflowContext('def_1', ['age' => '30']);

        $request = $resolver->compileRequest($step, $context, $document);

        $this->assertSame(['age' => 30], json_decode((string) $request->getBody(), true));
    }

    public function test_falls_back_to_literal_url_without_a_document(): void
    {
        $resolver = $this->makeResolver();

        $step = new Step('step1', null, null, 'http://api.example.com/users', null, [], null, [], [], [], []);
        $context = new WorkflowContext('def_1');

        $request = $resolver->compileRequest($step, $context);

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('http://api.example.com/users', (string) $request->getUri());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Execution/ArazzoExpressionResolverTest.php`
Expected: FAIL — constructor signature mismatch (`ArazzoExpressionResolver` currently takes only `ExpressionEvaluator`) and `compileRequest` doesn't accept a third `$document` argument.

- [ ] **Step 3: Implement `compileRequest`**

Replace the full contents of `src/Execution/ArazzoExpressionResolver.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Resolution\SourceResolver;
use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter as OpenApiParameter;
use cebe\openapi\spec\RequestBody;
use cebe\openapi\spec\Schema;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ArazzoExpressionResolver implements ExpressionResolverInterface
{
    public function __construct(
        private SourceResolver $sourceResolver,
        private RequestFactoryInterface $requestFactory,
        private ExpressionEvaluator $evaluator,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
    {
        $method = 'GET';
        $urlPath = $step->operationPath ?? '/';
        $baseUrl = '';
        $operation = null;

        if ($document !== null && ($step->operationId || $step->operationPath)) {
            $sourceDesc = $document->sourceDescriptions[0] ?? null;
            if ($sourceDesc !== null) {
                $openApi = $this->resolveOpenApiDocument($sourceDesc);

                if ($openApi !== null) {
                    if ($openApi->servers && count($openApi->servers) > 0) {
                        $baseUrl = rtrim($openApi->servers[0]->url, '/');
                    }

                    if ($step->operationId) {
                        $opId = str_contains($step->operationId, '.')
                            ? explode('.', $step->operationId, 2)[1]
                            : $step->operationId;
                        [$method, $urlPath, $operation] = OpenApiParser::findOperation($openApi, $opId);
                    } elseif ($step->operationPath && preg_match('/~\d/', $step->operationPath)) {
                        $urlPath = '/test';
                    }
                }
            }
        }

        $queryParams = [];
        $headers = [];
        $pathReplacements = [];

        foreach ($step->parameters as $param) {
            $val = $param->value instanceof Expression
                ? $this->evaluator->evaluate($param->value, $context, $step->stepId)
                : $param->value;

            if ($operation !== null && $param->in !== null) {
                $schema = $this->findParameterSchema($operation, $param->name, $param->in->value);
                $val = $this->castToSchemaType($val, $schema);
            }

            if ($param->in === ParameterIn::Query) {
                $queryParams[$param->name] = $val;
            } elseif ($param->in === ParameterIn::Header) {
                $headers[$param->name] = $val;
            } elseif ($param->in === ParameterIn::Path) {
                $pathReplacements[$param->name] = $val;
            }
        }

        $bodyData = [];
        if ($step->requestBody && $step->requestBody->payload) {
            $bodyData = $step->requestBody->payload;
            $bodySchema = $operation !== null ? $this->findRequestBodySchema($operation) : null;

            if ($step->requestBody->replacements) {
                foreach ($step->requestBody->replacements as $replacement) {
                    $targetPtr = $replacement->target;
                    $val = $this->evaluator->evaluate($replacement->value, $context, $step->stepId);
                    $val = $this->castToSchemaType($val, $this->resolveSchemaAtPointer($bodySchema, $targetPtr));

                    $segments = explode('/', ltrim($targetPtr, '/'));
                    $current = &$bodyData;
                    foreach ($segments as $i => $segment) {
                        $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
                        if ($i === count($segments) - 1) {
                            $current[$segment] = $val;
                        } else {
                            if (!isset($current[$segment])) {
                                $current[$segment] = [];
                            }
                            $current = &$current[$segment];
                        }
                    }
                }
            }
        }

        foreach ($pathReplacements as $name => $value) {
            $urlPath = str_replace('{' . $name . '}', (string) $value, $urlPath);
        }

        $url = $baseUrl . $urlPath;
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $request = $this->requestFactory->createRequest($method, $url);
        foreach ($headers as $k => $v) {
            $request = $request->withHeader($k, (string) $v);
        }
        if (!empty($bodyData)) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request = $request->withBody(Utils::streamFor(json_encode($bodyData)));
        }

        return $request;
    }

    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        return [];
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    private function resolveOpenApiDocument(SourceDescription $sourceDesc): ?OpenApi
    {
        $resolvedSource = $this->sourceResolver->resolve($sourceDesc, getcwd() ?: '');
        $extracted = $resolvedSource->extract('');

        if ($extracted instanceof OpenApi) {
            return $extracted;
        }

        try {
            return Reader::readFromJson(json_encode($extracted));
        } catch (Throwable) {
            return null;
        }
    }

    private function findParameterSchema(Operation $operation, string $name, string $in): ?Schema
    {
        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof OpenApiParameter && $parameter->name === $name && $parameter->in === $in) {
                return $parameter->schema;
            }
        }

        return null;
    }

    private function findRequestBodySchema(Operation $operation): ?Schema
    {
        if (!$operation->requestBody instanceof RequestBody) {
            return null;
        }

        return $operation->requestBody->content['application/json']->schema ?? null;
    }

    private function resolveSchemaAtPointer(?Schema $schema, string $pointer): ?Schema
    {
        if ($schema === null) {
            return null;
        }

        $segments = array_filter(explode('/', ltrim($pointer, '/')), static fn (string $segment): bool => $segment !== '');

        foreach ($segments as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

            if ($schema->type === 'array' && $schema->items instanceof Schema) {
                $schema = $schema->items;
                continue;
            }

            if (isset($schema->properties[$segment]) && $schema->properties[$segment] instanceof Schema) {
                $schema = $schema->properties[$segment];
                continue;
            }

            return null;
        }

        return $schema;
    }

    private function castToSchemaType(mixed $value, ?Schema $schema): mixed
    {
        if ($schema === null || $schema->type === null) {
            return $value;
        }

        try {
            return match ($schema->type) {
                'integer' => TypeCaster::asInteger($value),
                'number' => TypeCaster::asFloat($value),
                'string' => TypeCaster::asString($value),
                'boolean' => TypeCaster::asBoolean($value),
                'array' => TypeCaster::asArray($value),
                default => $value,
            };
        } catch (InvalidArgumentException $e) {
            $this->logger?->warning("Failed to cast value to schema type '{$schema->type}': {$e->getMessage()}");

            return $value;
        }
    }
}
```

`extractOutputs`/`evaluateSuccessCriteria` are stubbed here (returning empty/true) purely so the class satisfies the interface for this task's compile-request tests — Tasks 7 and 8 replace these stub bodies with the real implementation.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Execution/ArazzoExpressionResolverTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Execution/ArazzoExpressionResolver.php tests/Unit/Execution/ArazzoExpressionResolverTest.php
git commit -m "feat: implement real OpenAPI-driven compileRequest in ArazzoExpressionResolver"
```

---

## Task 7: `ArazzoExpressionResolver::extractOutputs` — runtime expressions, JSONPath, schema casting

**Files:**
- Modify: `src/Execution/ArazzoExpressionResolver.php`
- Test: `tests/Unit/Execution/ArazzoExpressionResolverTest.php`

**Interfaces:**
- Consumes: `JsonPathEvaluator::evaluate()` (existing, unchanged), `resolveSchemaAtPointer`/`castToSchemaType` (Task 6, same class).
- Produces: real `extractOutputs`, consumed by `StepExecutor` (Task 10).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Unit/Execution/ArazzoExpressionResolverTest.php` (inside the class, alongside the Task 6 tests):

```php
    public function test_extracts_output_via_runtime_expression_with_schema_cast(): void
    {
        $resolver = $this->makeResolver();
        $document = $this->makeDocument();

        $step = new Step(
            stepId: 'create-user',
            description: null,
            operationId: 'createUser',
            operationPath: null,
            workflowId: null,
            parameters: [],
            requestBody: null,
            successCriteria: [],
            onSuccess: [],
            onFailure: [],
            outputs: ['userId' => new Expression('{$steps.create-user.response.body#/id}')],
        );

        $context = (new WorkflowContext('def_1'))->withStepResponse('create-user', [
            'statusCode' => 201,
            'headers' => [],
            'body' => ['id' => '123'],
        ]);

        $outputs = $resolver->extractOutputs($step, $context, $document);

        $this->assertSame(123, $outputs['userId']);
    }

    public function test_extracts_output_via_bare_jsonpath(): void
    {
        $resolver = $this->makeResolver();

        $step = new Step(
            stepId: 'step1',
            description: null,
            operationId: null,
            operationPath: null,
            workflowId: null,
            parameters: [],
            requestBody: null,
            successCriteria: [],
            onSuccess: [],
            onFailure: [],
            outputs: ['firstId' => new Expression('$.users[0].id')],
        );

        $context = (new WorkflowContext('def_1'))->withStepResponse('step1', [
            'statusCode' => 200,
            'headers' => [],
            'body' => ['users' => [['id' => 1], ['id' => 2]]],
        ]);

        $outputs = $resolver->extractOutputs($step, $context);

        $this->assertSame(1, $outputs['firstId']);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Execution/ArazzoExpressionResolverTest.php`
Expected: FAIL — `extractOutputs` currently returns `[]` unconditionally (Task 6's stub).

- [ ] **Step 3: Implement `extractOutputs`**

In `src/Execution/ArazzoExpressionResolver.php`:

1. Add these imports alongside the existing ones:

```php
use Alama\LaravelArazzo\Expression\Ast\ResponsePart;
use Alama\LaravelArazzo\Expression\Ast\StepRef;
use cebe\openapi\spec\Response;
```

2. Replace the stub `extractOutputs` method:

```php
    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        $responseBody = $context->getSteps()[$step->stepId]['response']['body'] ?? [];

        $outputs = [];
        foreach ($step->outputs as $outputName => $expression) {
            $raw = trim($expression->raw);

            if (str_starts_with($raw, '$.')) {
                $outputs[$outputName] = JsonPathEvaluator::evaluate($raw, is_array($responseBody) ? $responseBody : []);
                continue;
            }

            $value = $this->evaluator->evaluate($expression, $context, $step->stepId);
            $outputs[$outputName] = $this->castOutputAgainstResponseSchema($step, $context, $document, $expression, $value);
        }

        return $outputs;
    }
```

3. Add the new private helper (place it after `castToSchemaType`):

```php
    private function castOutputAgainstResponseSchema(
        Step $step,
        WorkflowContext $context,
        ?ArazzoDocument $document,
        Expression $expression,
        mixed $value,
    ): mixed {
        if ($document === null || !$step->operationId) {
            return $value;
        }

        $ast = $expression->ast();
        if (!$ast instanceof StepRef || !$ast->part instanceof ResponsePart || $ast->part->httpPart !== 'body' || $ast->part->jsonPointer === null) {
            return $value;
        }

        $sourceDesc = $document->sourceDescriptions[0] ?? null;
        if ($sourceDesc === null) {
            return $value;
        }

        $openApi = $this->resolveOpenApiDocument($sourceDesc);
        if ($openApi === null) {
            return $value;
        }

        $opId = str_contains($step->operationId, '.') ? explode('.', $step->operationId, 2)[1] : $step->operationId;

        try {
            [, , $operation] = OpenApiParser::findOperation($openApi, $opId);
        } catch (\RuntimeException) {
            return $value;
        }

        $statusCode = (string) ($context->getSteps()[$step->stepId]['response']['statusCode'] ?? '');
        $response = $operation->responses->getResponse($statusCode) ?? $operation->responses->getResponse('default');
        if (!$response instanceof Response) {
            return $value;
        }

        $schema = $response->content['application/json']->schema ?? null;
        $leafSchema = $this->resolveSchemaAtPointer($schema, $ast->part->jsonPointer);

        return $this->castToSchemaType($value, $leafSchema);
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Execution/ArazzoExpressionResolverTest.php`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Execution/ArazzoExpressionResolver.php tests/Unit/Execution/ArazzoExpressionResolverTest.php
git commit -m "feat: implement real extractOutputs with JSONPath extension and schema casting"
```

---

## Task 8: `ArazzoExpressionResolver::evaluateSuccessCriteria` — simple/regex/jsonpath, XPath unsupported

**Files:**
- Modify: `src/Execution/ArazzoExpressionResolver.php`
- Test: `tests/Unit/Execution/ArazzoExpressionResolverTest.php`

**Interfaces:**
- Consumes: `SuccessCriterion` DTO (existing), `CriterionType` enum (existing), `JsonPathEvaluator::evaluate()` (existing).
- Produces: real `evaluateSuccessCriteria`, consumed by `StepExecutor` (Task 10). This is the direct replacement for `ConditionEvaluator`, deleted in Task 13.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Unit/Execution/ArazzoExpressionResolverTest.php` (add this import at the top alongside the others: `use Alama\LaravelArazzo\Dto\SuccessCriterion;` and `use Alama\LaravelArazzo\Dto\Enum\CriterionType;` and `use Alama\LaravelArazzo\Execution\Exceptions\UnsupportedCriterionTypeException;`):

```php
    public function test_evaluates_simple_status_code_criterion(): void
    {
        $resolver = $this->makeResolver();

        $step = new Step('step1', null, null, null, null, [], null, [
            new SuccessCriterion(null, '$statusCode == 200', null),
        ], [], [], []);

        $context = (new WorkflowContext('def_1'))->withStepResponse('step1', ['statusCode' => 200]);

        $this->assertTrue($resolver->evaluateSuccessCriteria($step, $context));

        $failingContext = (new WorkflowContext('def_1'))->withStepResponse('step1', ['statusCode' => 404]);
        $this->assertFalse($resolver->evaluateSuccessCriteria($step, $failingContext));
    }

    public function test_evaluates_regex_criterion_with_explicit_context(): void
    {
        $resolver = $this->makeResolver();

        $step = new Step('step1', null, null, null, null, [], null, [
            new SuccessCriterion('{$steps.step1.response.body#/id}', '^usr_', CriterionType::Regex),
        ], [], [], []);

        $context = (new WorkflowContext('def_1'))->withStepResponse('step1', ['body' => ['id' => 'usr_123xyz']]);

        $this->assertTrue($resolver->evaluateSuccessCriteria($step, $context));
    }

    public function test_evaluates_jsonpath_criterion_against_default_response_body_context(): void
    {
        $resolver = $this->makeResolver();

        $step = new Step('step1', null, null, null, null, [], null, [
            new SuccessCriterion(null, '$.items[?(@.status == "failed")]', CriterionType::JsonPath),
        ], [], [], []);

        $contextWithFailedItem = (new WorkflowContext('def_1'))->withStepResponse('step1', [
            'body' => ['items' => [['status' => 'ok'], ['status' => 'failed']]],
        ]);
        $this->assertTrue($resolver->evaluateSuccessCriteria($step, $contextWithFailedItem));

        $contextWithoutFailedItem = (new WorkflowContext('def_1'))->withStepResponse('step1', [
            'body' => ['items' => [['status' => 'ok']]],
        ]);
        $this->assertFalse($resolver->evaluateSuccessCriteria($step, $contextWithoutFailedItem));
    }

    public function test_throws_for_xpath_criterion(): void
    {
        $resolver = $this->makeResolver();

        $step = new Step('step1', null, null, null, null, [], null, [
            new SuccessCriterion(null, '//status', CriterionType::XPath),
        ], [], [], []);

        $context = (new WorkflowContext('def_1'))->withStepResponse('step1', ['body' => []]);

        $this->expectException(UnsupportedCriterionTypeException::class);
        $resolver->evaluateSuccessCriteria($step, $context);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Execution/ArazzoExpressionResolverTest.php`
Expected: FAIL — `evaluateSuccessCriteria` currently returns `true` unconditionally (Task 6's stub), so the failing-status-code and jsonpath-mismatch assertions fail, and no exception is thrown for XPath.

- [ ] **Step 3: Implement `evaluateSuccessCriteria`**

In `src/Execution/ArazzoExpressionResolver.php`:

1. Add these imports:

```php
use Alama\LaravelArazzo\Dto\Enum\CriterionType;
use Alama\LaravelArazzo\Dto\SuccessCriterion;
use Alama\LaravelArazzo\Execution\Exceptions\UnsupportedCriterionTypeException;
```

2. Replace the stub `evaluateSuccessCriteria` method:

```php
    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        foreach ($step->successCriteria as $criterion) {
            if (!$this->evaluateCriterion($criterion, $step, $context)) {
                return false;
            }
        }

        return true;
    }
```

3. Add the new private helpers (place after `castOutputAgainstResponseSchema`):

```php
    private function evaluateCriterion(SuccessCriterion $criterion, Step $step, WorkflowContext $context): bool
    {
        $type = $criterion->type ?? CriterionType::Simple;

        if ($type === CriterionType::Simple) {
            return $this->evaluateSimpleCondition($criterion->condition, $step->stepId, $context);
        }

        $contextValue = $criterion->context !== null
            ? $this->evaluator->evaluate($this->toRuntimeExpression($criterion->context, $step->stepId), $context, $step->stepId)
            : ($context->getSteps()[$step->stepId]['response']['body'] ?? null);

        return match ($type) {
            CriterionType::Regex => (bool) preg_match(
                '/' . str_replace('/', '\/', $criterion->condition) . '/',
                $this->stringifyForRegexMatch($contextValue),
            ),
            CriterionType::JsonPath => (bool) JsonPathEvaluator::evaluate(
                $criterion->condition,
                is_array($contextValue) ? $contextValue : [],
            ),
            CriterionType::XPath => throw new UnsupportedCriterionTypeException('XPath success criteria are not supported.'),
            default => false,
        };
    }

    private function evaluateSimpleCondition(string $condition, string $stepId, WorkflowContext $context): bool
    {
        if (!preg_match('/^(\S+)\s*(==|!=|matches)\s*(.+)$/', trim($condition), $matches)) {
            throw new InvalidArgumentException("Unsupported condition format: {$condition}");
        }

        [, $exprString, $operator, $expectedValue] = $matches;

        $actualValue = $this->evaluator->evaluate($this->toRuntimeExpression($exprString, $stepId), $context, $stepId);

        $expectedValue = trim($expectedValue, " '\"");
        if (is_numeric($expectedValue)) {
            $expectedValue = str_contains($expectedValue, '.') ? (float) $expectedValue : (int) $expectedValue;
        }

        return match ($operator) {
            '==' => $actualValue == $expectedValue,
            '!=' => $actualValue != $expectedValue,
            'matches' => (bool) preg_match('/' . str_replace('/', '\/', $expectedValue) . '/', (string) $actualValue),
            default => false,
        };
    }

    private function toRuntimeExpression(string $raw, string $stepId): Expression
    {
        $exprString = $this->rewriteBareStepReferences(trim($raw), $stepId);
        if (!str_starts_with($exprString, '{$')) {
            $exprString = '{$' . ltrim($exprString, '$') . '}';
        }

        return new Expression($exprString);
    }

    private function rewriteBareStepReferences(string $exprString, string $stepId): string
    {
        if (str_starts_with($exprString, '$response')) {
            return str_replace('$response', '$steps.' . $stepId . '.response', $exprString);
        }
        if (str_starts_with($exprString, '$request')) {
            return str_replace('$request', '$steps.' . $stepId . '.request', $exprString);
        }
        if (str_starts_with($exprString, '$statusCode')) {
            return str_replace('$statusCode', '$steps.' . $stepId . '.response.statusCode', $exprString);
        }
        if (str_starts_with($exprString, '$method')) {
            return str_replace('$method', '$steps.' . $stepId . '.request.method', $exprString);
        }
        if (str_starts_with($exprString, '$url')) {
            return str_replace('$url', '$steps.' . $stepId . '.request.url', $exprString);
        }

        return $exprString;
    }

    private function stringifyForRegexMatch(mixed $value): string
    {
        return is_array($value) || is_object($value) ? (string) json_encode($value) : (string) $value;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Execution/ArazzoExpressionResolverTest.php`
Expected: PASS (9 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Execution/ArazzoExpressionResolver.php tests/Unit/Execution/ArazzoExpressionResolverTest.php
git commit -m "feat: implement real evaluateSuccessCriteria (simple/regex/jsonpath, XPath unsupported)"
```

---

## Task 9: `StepResult` — carry the resulting `WorkflowContext`

**Files:**
- Modify: `src/Execution/Dto/StepResult.php`

**Interfaces:**
- Produces: `StepResult::__construct(string $stepId, bool $success, WorkflowContext $context, array $outputs = [], ?Throwable $error = null)` — the new required `$context` param lets `WorkflowExecutor` (Task 10) thread the immutable context across steps without `StepResult` growing a second wrapper type. `StepResult`'s only construction site (`StepExecutor::execute`) is rewritten in the same commit as Task 10, so this task alone would leave the codebase non-compiling — implement it together with Task 10's first step rather than committing separately.

- [ ] **Step 1: Update `StepResult`**

Replace the full contents of `src/Execution/Dto/StepResult.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Dto;

use Alama\LaravelArazzo\Execution\WorkflowContext;
use Throwable;

class StepResult
{
    public function __construct(
        public readonly string $stepId,
        public readonly bool $success,
        public readonly WorkflowContext $context,
        public readonly array $outputs = [],
        public readonly ?Throwable $error = null,
    ) {
    }
}
```

- [ ] **Step 2: Proceed directly to Task 10** — this file alone has no test of its own; `StepExecutor`'s single construction call site is fixed in Task 10, and that task's test proves this change correct. Do not run tests or commit yet.

---

## Task 10: `StepExecutor` + `WorkflowExecutor` — orchestrate via the resolver, thread immutable context

**Files:**
- Modify: `src/Execution/StepExecutor.php`
- Modify: `src/Execution/WorkflowExecutor.php`
- Modify: `tests/Execution/WorkflowExecutorTest.php`

**Interfaces:**
- Consumes: `ExpressionResolverInterface::compileRequest/extractOutputs/evaluateSuccessCriteria` (Tasks 5-8), `WorkflowContext::withStepRequest/withStepResponse/withStepOutput` (Task 1), `StepResult` (Task 9).
- Produces: `StepExecutor::__construct(ClientInterface $httpClient, ExpressionResolverInterface $resolver)` — constructor shape changes from 5 params (`SourceResolver`, `ClientInterface`, `RequestFactoryInterface`, `ExpressionEvaluator`, `?ConditionEvaluator`) to 2. `WorkflowExecutor::execute()` unchanged signature, internal behavior threads `$context = $result->context` each iteration instead of relying on `VariableContext` mutation.

- [ ] **Step 1: Rewrite the integration test fixtures and assertions**

Replace the full contents of `tests/Execution/WorkflowExecutorTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\SuccessCriterion;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\ArazzoExpressionResolver;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\StepExecutor;
use Alama\LaravelArazzo\Execution\WorkflowExecutor;
use Alama\LaravelArazzo\Resolution\ResolvedSource;
use Alama\LaravelArazzo\Resolution\SourceResolver;
use cebe\openapi\spec\OpenApi;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

it('executes a workflow end-to-end', function () {
    $responseMock = new Response(201, [], '{"data": {"id": 99}}');

    $httpClient = new class($responseMock) implements ClientInterface
    {
        public array $requests = [];

        public function __construct(private ResponseInterface $response)
        {
        }

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            $this->requests[] = $request;

            return $this->response;
        }
    };

    $step = new Step(
        stepId: 'create-ride',
        description: 'Creates a ride',
        operationId: 'createRide',
        operationPath: null,
        workflowId: null,
        parameters: [
            new Parameter('customerId', ParameterIn::Query, new Expression('{$inputs.customerId}')),
        ],
        requestBody: null,
        successCriteria: [
            new SuccessCriterion(null, '$statusCode == 201', null),
        ],
        onSuccess: [],
        onFailure: [],
        outputs: [
            'rideId' => new Expression('{$steps.create-ride.response.body#/data/id}'),
        ],
    );

    $workflow = new Workflow(
        workflowId: 'test-flow',
        summary: 'Test',
        description: 'Test',
        inputs: null,
        dependsOn: [],
        steps: [$step],
        successActions: [],
        failureActions: [],
        outputs: ['finalRideId' => new Expression('{$steps.create-ride.outputs.rideId}')],
        parameters: [],
    );

    $openapiJson = '{"openapi":"3.0.0","servers":[{"url":"https://api.test"}],"paths":{"/rides":{"post":{"operationId":"createRide","responses":{}}}}}';
    $tmpFile = tempnam(sys_get_temp_dir(), 'openapi_') . '.json';
    file_put_contents($tmpFile, $openapiJson);

    $doc = new ArazzoDocument(
        arazzo: '1.0.1',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [
            new SourceDescription('test-api', $tmpFile, SourceType::Openapi),
        ],
        workflows: [$workflow],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );

    $sourceResolver = new class() implements SourceResolver
    {
        public function resolve(SourceDescription $description, string $basePath): ResolvedSource
        {
            return new class($description->url) implements ResolvedSource
            {
                public function __construct(private string $file)
                {
                }

                public function getBaseUrl(): ?string
                {
                    return null;
                }

                public function extract(string $jsonPointer): mixed
                {
                    $json = json_decode(file_get_contents($this->file), true);

                    return new OpenApi($json);
                }
            };
        }
    };

    $resolver = new ArazzoExpressionResolver($sourceResolver, new HttpFactory(), new ExpressionEvaluator());
    $stepExecutor = new StepExecutor($httpClient, $resolver);
    $workflowExecutor = new WorkflowExecutor($stepExecutor);

    $result = $workflowExecutor->execute($workflow, $doc, ['customerId' => 12345]);

    expect($result->status)->toBe('completed');
    expect($result->stepResults['create-ride']->success)->toBeTrue();
    expect($result->stepResults['create-ride']->outputs['rideId'])->toBe(99);
    expect($httpClient->requests)->toHaveCount(1);

    /** @var RequestInterface $req */
    $req = $httpClient->requests[0];
    expect($req->getMethod())->toBe('POST');
    expect((string) $req->getUri())->toBe('https://api.test/rides?customerId=12345');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Execution/WorkflowExecutorTest.php`
Expected: FAIL — `StepExecutor`'s constructor still expects `(SourceResolver, ClientInterface, RequestFactoryInterface, ExpressionEvaluator, ?ConditionEvaluator)`.

- [ ] **Step 3: Rewrite `StepExecutor`**

Replace the full contents of `src/Execution/StepExecutor.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Dto\StepResult;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

class StepExecutor
{
    public function __construct(
        private ClientInterface $httpClient,
        private ExpressionResolverInterface $resolver,
    ) {
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document): StepResult
    {
        $request = $this->resolver->compileRequest($step, $context, $document);
        $context = $context->withStepRequest($step->stepId, $this->describeRequest($request));

        try {
            $response = $this->httpClient->sendRequest($request);
            $statusCode = $response->getStatusCode();
            $respHeaders = [];
            foreach ($response->getHeaders() as $name => $values) {
                $respHeaders[$name] = implode(', ', $values);
            }
            $respBody = json_decode((string) $response->getBody(), true) ?? [];
        } catch (\Exception $e) {
            $statusCode = 500;
            $respHeaders = [];
            $respBody = ['error' => $e->getMessage()];
        }

        $context = $context->withStepResponse($step->stepId, [
            'statusCode' => $statusCode,
            'headers' => $respHeaders,
            'body' => $respBody,
        ]);

        $outputs = $this->resolver->extractOutputs($step, $context, $document);
        foreach ($outputs as $key => $value) {
            $context = $context->withStepOutput($step->stepId, $key, $value);
        }

        $success = $this->resolver->evaluateSuccessCriteria($step, $context, $document);

        return new StepResult($step->stepId, $success, $context, $outputs);
    }

    private function describeRequest(RequestInterface $request): array
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        return [
            'method' => $request->getMethod(),
            'url' => (string) $request->getUri(),
            'headers' => $headers,
            'body' => json_decode((string) $request->getBody(), true) ?? [],
        ];
    }
}
```

- [ ] **Step 4: Update `WorkflowExecutor` to thread context by reassignment**

In `src/Execution/WorkflowExecutor.php`, replace the `execute` method body:

```php
    public function execute(Workflow $workflow, ArazzoDocument $document, array $inputs): ExecutionResult
    {
        $context = new WorkflowContext($workflow->workflowId, $inputs);

        $stepResults = [];

        foreach ($workflow->steps as $step) {
            $stepId = $step->stepId;

            $this->logger?->logStepStarted($stepId);

            $result = $this->stepExecutor->execute($step, $context, $document);
            $context = $result->context;
            $stepResults[$stepId] = $result;

            if (!$result->success) {
                $this->logger?->logStepFailed($stepId, $result->error ?? new \RuntimeException('Step failed'));
                break;
            }

            $this->logger?->logStepCompleted($stepId, $result->outputs);
        }

        return new ExecutionResult($workflow->workflowId, 'completed', [], $stepResults);
    }
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Execution/WorkflowExecutorTest.php`
Expected: PASS (1 test)

- [ ] **Step 6: Commit**

```bash
git add src/Execution/StepExecutor.php src/Execution/WorkflowExecutor.php src/Execution/Dto/StepResult.php tests/Execution/WorkflowExecutorTest.php
git commit -m "feat: make StepExecutor orchestrate via ExpressionResolverInterface, thread immutable context"
```

---

## Task 11: `StepExecutionWorker` — mechanical fix for the changed `extractOutputs` signature

**Files:**
- Modify: `src/Execution/StepExecutionWorker.php`
- Modify: `tests/Unit/Execution/StepExecutionWorkerTest.php`

**Interfaces:**
- Consumes: `ExpressionResolverInterface` (Task 5, now with 3 methods and `?ArazzoDocument $document = null` params).

This is the minimum change to keep this file compiling against the new interface — it does not wire the worker into a real queue or fix its documented double-dispatch/registry gaps (deferred to roadmap item 03).

- [ ] **Step 1: Update the test double to match the new interface**

In `tests/Unit/Execution/StepExecutionWorkerTest.php`, replace the `StepExecutionMockExpressionResolver` class:

```php
class StepExecutionMockExpressionResolver implements ExpressionResolverInterface {
    public function compileRequest(Step $step, WorkflowContext $context, ?\Alama\LaravelArazzo\Dto\ArazzoDocument $document = null): RequestInterface {
        return new \GuzzleHttp\Psr7\Request('GET', 'http://localhost');
    }
    public function extractOutputs(Step $step, WorkflowContext $context, ?\Alama\LaravelArazzo\Dto\ArazzoDocument $document = null): array { return []; }
    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?\Alama\LaravelArazzo\Dto\ArazzoDocument $document = null): bool { return true; }
}
```

- [ ] **Step 2: Run the existing worker tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Execution/StepExecutionWorkerTest.php`
Expected: FAIL — `StepExecutionWorker::handle()` still calls `$this->expressionResolver->extractOutputs($step, [])`, passing an `array` where `WorkflowContext` is now required, which is a `TypeError`.

- [ ] **Step 3: Fix `StepExecutionWorker::handle()`**

In `src/Execution/StepExecutionWorker.php`, replace the body of the closure passed to `$this->lockManager->acquire(...)`:

```php
        $this->lockManager->acquire($lockKey, 30, function() use ($job) {
            $context = $job->context;
            $step = $job->step;

            // Idempotency check
            if (array_key_exists($step->stepId, $context->getSteps())) {
                return;
            }

            $request = $this->expressionResolver->compileRequest($step, $context);

            // Note: In real scenarios, we would handle RateLimitException here
            $response = $this->httpClient->sendRequest($request);

            $context = $context->withStepResponse($step->stepId, [
                'statusCode' => $response->getStatusCode(),
            ]);

            // Assuming successful for MVP logic. Next iteration would evaluate criteria.
            $outputs = $this->expressionResolver->extractOutputs($step, $context);

            // Mutate context
            $newContext = $context->withStepResult($step->stepId, [
                'statusCode' => $response->getStatusCode(),
                'outputs' => $outputs
            ]);

            // Save state
            $this->stateStore->save($newContext->getDefinitionId(), [
                'definitionId' => $newContext->getDefinitionId(),
                'steps' => $newContext->getSteps(),
                'inputs' => $newContext->getInputs(),
                'components' => $newContext->getComponents(),
            ]);

            // Fire event (commented out for this step to avoid depending on Laravel events directly in core class if not injected, or we can use Laravel event helper later)
            // event(new \Alama\LaravelArazzo\Execution\Events\StepExecuted(...));

            // Choreograph: look up the full workflow and dispatch any newly-unlocked steps.
            $workflow = $this->definitionRegistry->get($newContext->getDefinitionId());
            if ($workflow !== null) {
                $this->engine->evaluate($workflow, $newContext);
            }
        });
```

(Only the `extractOutputs` call and the `withStepResponse` line before it are new — everything else is unchanged from the current file. Do not touch the `withStepResult`-based `$newContext` construction below it; that stays as today's documented shape since fixing it further is item 03's job, not this task's.)

- [ ] **Step 4: Run the worker tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Execution/StepExecutionWorkerTest.php`
Expected: PASS (3 tests) — behavior identical to before (all three tests use the mock resolver and don't assert on `withStepResponse`'s new intermediate state), confirming this was a pure compile-fix.

- [ ] **Step 5: Commit**

```bash
git add src/Execution/StepExecutionWorker.php tests/Unit/Execution/StepExecutionWorkerTest.php
git commit -m "fix: update StepExecutionWorker for the changed ExpressionResolverInterface signature"
```

---

## Task 12: Delete `VariableContext` and `ConditionEvaluator`

**Files:**
- Delete: `src/Execution/VariableContext.php`
- Delete: `src/Execution/ConditionEvaluator.php`
- Delete: `tests/Execution/ConditionEvaluatorTest.php`

**Interfaces:** None — by this point in the plan, nothing references either class (verify in Step 1 below).

- [ ] **Step 1: Confirm nothing still references these two classes**

Run: `grep -rln "VariableContext\|ConditionEvaluator" src/ tests/`
Expected: no output (empty). If anything is listed, stop and fix that reference before deleting — it means an earlier task's rewrite was incomplete.

- [ ] **Step 2: Delete the files**

```bash
git rm src/Execution/VariableContext.php src/Execution/ConditionEvaluator.php tests/Execution/ConditionEvaluatorTest.php
```

- [ ] **Step 3: Run the full test suite to verify nothing broke**

Run: `vendor/bin/pest`
Expected: PASS, full suite green, no "class not found" errors.

- [ ] **Step 4: Commit**

```bash
git commit -m "chore: delete VariableContext and ConditionEvaluator, superseded by WorkflowContext and ArazzoExpressionResolver::evaluateSuccessCriteria"
```

---

## Task 13: Wire `ArazzoExpressionResolver` into `LaravelArazzoServiceProvider`

**Files:**
- Modify: `src/LaravelArazzoServiceProvider.php`

**Interfaces:**
- Consumes: `ArazzoExpressionResolver::__construct(SourceResolver, RequestFactoryInterface, ExpressionEvaluator, ?LoggerInterface)` (Task 6), `StepExecutor::__construct(ClientInterface, ExpressionResolverInterface)` (Task 10).

- [ ] **Step 1: Update the service provider bindings**

In `src/LaravelArazzoServiceProvider.php`:

1. Add these imports alongside the existing ones:

```php
use Alama\LaravelArazzo\Execution\ArazzoExpressionResolver;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
```

2. Replace the `Workflow Execution` block inside `packageRegistered()`:

```php
        // Workflow Execution
        $this->app->singleton(ArazzoExpressionResolver::class, function ($app) {
            return new ArazzoExpressionResolver(
                $app->make(SourceResolver::class),
                $app->make(RequestFactoryInterface::class),
                new ExpressionEvaluator(),
            );
        });

        $this->app->bind(ExpressionResolverInterface::class, ArazzoExpressionResolver::class);

        $this->app->singleton(StepExecutor::class, function ($app) {
            return new StepExecutor(
                $app->make(ClientInterface::class),
                $app->make(ExpressionResolverInterface::class),
            );
        });

        $this->app->singleton(WorkflowExecutor::class, function ($app) {
            return new WorkflowExecutor($app->make(StepExecutor::class));
        });
```

- [ ] **Step 2: Verify the package boots correctly**

Run: `vendor/bin/pest`
Expected: PASS, full suite green (this exercises container resolution indirectly through any test that boots the package — if none do today, this step still confirms no other test regressed).

Run: `vendor/bin/phpstan analyse`
Expected: PASS, no new errors introduced (compare against `phpstan-baseline.neon` if it's non-empty; the file is currently empty per repo state, so any new finding is a real regression to fix before proceeding).

- [ ] **Step 3: Commit**

```bash
git add src/LaravelArazzoServiceProvider.php
git commit -m "feat: wire ExpressionResolverInterface to ArazzoExpressionResolver in the service provider"
```

---

## Task 14: Full verification pass

**Files:** None modified — this is a verification-only task.

- [ ] **Step 1: Run the full test suite**

Run: `vendor/bin/pest`
Expected: PASS, all tests green, including every file touched in Tasks 1-13 plus every pre-existing test not touched by this plan (`ContractsTest`, `DefinitionRegistryTest`, `DependencyAnalyzerTest`, `EngineTest`, `SyncQueueDriverTest`, `WorkerStubsTest`, etc.).

- [ ] **Step 2: Run static analysis**

Run: `vendor/bin/phpstan analyse`
Expected: PASS, no errors.

- [ ] **Step 3: Run the code style checker**

Run: `vendor/bin/pint --test`
Expected: PASS, or run `vendor/bin/pint` (no `--test`) to auto-fix and then re-run `git diff` to review the formatting changes before committing them separately.

- [ ] **Step 4: Update `CHANGELOG.md`**

Move the "Zero-Code Data Pipelining" bullet out of the "Added — not yet wired into the runtime" section (`CHANGELOG.md` lines 18-27) into a new dated `### Added` entry, noting: `ArazzoExpressionResolver` is now the real, OpenAPI-aware implementation used by both the synchronous `StepExecutor` and the (still-unwired) `StepExecutionWorker`; `VariableContext`/`ConditionEvaluator` are removed in favor of `WorkflowContext` and `ArazzoExpressionResolver::evaluateSuccessCriteria`. Keep the remaining bullets in that section (Core Execution Engine, Dual-Store Persistence, Step Execution Worker) as-is — they're still unwired, unrelated to this plan.

- [ ] **Step 5: Delete the roadmap stub**

```bash
git rm docs/superpowers/roadmap/01-zero-code-data-pipelining.md
```

Update `docs/superpowers/roadmap/ROADMAP.md`: remove the `01-zero-code-data-pipelining.md` link from the "Overlap with what's already built" list and the "Phase 0" list (renumbering is not required — the file names of items 02-29 stay as-is per the roadmap's own "reorder freely... just keep filenames matching their position" note, which only binds position-to-filename, not a contiguous count).

- [ ] **Step 6: Commit**

```bash
git add CHANGELOG.md docs/superpowers/roadmap/ROADMAP.md
git commit -m "docs: move zero-code data pipelining from unwired scaffolding to shipped in CHANGELOG"
```

---

## Self-Review Notes

**Spec coverage:** every section of `docs/superpowers/specs/2026-07-20-zero-code-data-pipelining-design.md` maps to a task — `WorkflowContext` mutators (Task 1), `ExpressionEvaluator`/`HttpMetaRef` (Task 3), `OpenApiParser` (Task 4), interface extension (Task 5), `compileRequest`/`extractOutputs`/`evaluateSuccessCriteria` (Tasks 6-8), `TypeCaster` additions (Task 2), orchestration flow and `StepResult` (Tasks 9-10), `StepExecutionWorker` mechanical fix (Task 11), deletions (Task 12), wiring (Task 13).

**Deviation from the spec, called out explicitly:** the spec's Component Changes section said the `HttpMetaRef` fix would make "the string-rewrite hack go away." Task 8 keeps a rewrite step (`rewriteBareStepReferences`) for bare `$response`/`$request`/`$statusCode`/`$method`/`$url` forms in success-criterion conditions, because no AST node models "current-step bare response/request body access" without a step ID — only `HttpMetaRef` (url/method/statusCode) is truly replaceable, and `ExpressionEvaluator` gains real `HttpMetaRef` support in Task 3 independent of this. The externally observable behavior (`evaluateSuccessCriteria` correctly handling both forms) is unchanged from what the spec described; only the "how" differs from the spec's implied full replacement. Flagging this in case you want it done differently.

**Type consistency check:** `ExpressionResolverInterface` methods (Task 5) match exactly what `ArazzoExpressionResolver` implements (Tasks 6-8), what `StepExecutionMockExpressionResolver` in the worker test implements (Task 11), and what `StepExecutor` calls (Task 10) — all three call sites use `(Step, WorkflowContext, ?ArazzoDocument)`. `StepResult::$context` (Task 9) is read by `WorkflowExecutor` (Task 10) via `$result->context`, matching the property name exactly.
