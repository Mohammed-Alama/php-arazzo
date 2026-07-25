# Idempotency & Replay Safeguards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Auto-inject a deterministic `Idempotency-Key` header on mutating HTTP requests emitted by the engine, opt-in via config with per-step override, so manual and automatic replays of a step reuse the same key and let the upstream API dedup.

**Architecture:** A new stateless `IdempotencyKeyInjector` computes `sha256(definitionId + workflowId + stepId + requestFingerprint)` where the fingerprint hashes the compiled PSR-7 request's method, URI, and canonicalized body. Both execution paths — `StepExecutor` (sync) and `HttpStepExecutor` (async) — call the injector after `compileRequest()` and use the mutated request for the actual send. The async path also emits a `step.idempotency_key_injected` event through the existing `EventLedgerInterface`.

**Tech Stack:** PHP 8.2+, PSR-7 (`Psr\Http\Message\RequestInterface`), Pest v3, Laravel package skeleton (Spatie), existing `Alama\LaravelArazzo\*` namespace.

## Global Constraints

- All new classes: `declare(strict_types=1);` at the top, `final` where the spec doesn't require inheritance.
- Follow existing style: named-argument construction preferred at call sites where legacy positional forms don't already exist; value objects are `final readonly` (see `StepExecutionOutcome`).
- Config extension pattern: `env(...)` call is the only place `env()` may be called; each `env()` line carries `/** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */` (matches existing entries in `config/arazzo.php`).
- Test namespaces: `namespace Tests\Execution;` for `tests/Execution/*` files (matches existing files like `StepExecutorTest.php`, `HttpStepExecutorTest.php`); `namespace Alama\LaravelArazzo\Tests\Parser;` for `tests/Parser/*` (matches `ParserTest.php`).
- Never bypass hooks (`--no-verify`, `--no-gpg-sign`) when committing.
- Every task ends with running the relevant test file, then the full `vendor/bin/pest` suite, then a single commit.

---

## File Structure

**Create:**
- `src/Execution/IdempotencyKeyInjector.php` — the injector class.
- `src/Execution/InjectionResult.php` — value object carrying the mutated request plus injected key/header (mirrors the `StepExecutionOutcome.php` pattern of small `final readonly` value objects in `src/Execution/`).
- `tests/Execution/IdempotencyKeyInjectorTest.php` — full test suite for the injector.

**Modify:**
- `src/Dto/Step.php` — append `?bool $idempotencyKey` and `?string $idempotencyHeader` as trailing defaulted constructor parameters.
- `src/Parser/Parser.php` — read `x-idempotency-key` and `x-idempotency-header` inside `parseStep()`.
- `config/arazzo.php` — add nested `idempotency.enabled` / `idempotency.header` map.
- `src/Execution/StepExecutor.php` — constructor grows an optional injector; `execute()` calls it after `compileRequest()`.
- `src/Execution/HttpStepExecutor.php` — constructor grows an optional injector + optional event ledger; `execute()` calls the injector and emits the ledger event.
- `src/LaravelArazzoServiceProvider.php` — new singleton binding for `IdempotencyKeyInjector`; extend `StepExecutor::class` and `HttpStepExecutor::class` bindings to inject the new deps.
- `tests/Execution/StepExecutorTest.php` — add coverage for the injector wiring on the sync path.
- `tests/Execution/HttpStepExecutorTest.php` — add coverage for the injector wiring + ledger event on the async path.
- `tests/Parser/ParserTest.php` — cover the two new extensions.
- `tests/LaravelArazzoServiceProviderBindingsTest.php` — assert the singleton binding and config-driven behavior.

---

## Task 1: `Step` DTO fields + parser extensions

**Files:**
- Modify: `src/Dto/Step.php`
- Modify: `src/Parser/Parser.php` (`parseStep()`)
- Test: `tests/Parser/ParserTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `Step::$idempotencyKey: ?bool` (null = "use global default"), `Step::$idempotencyHeader: ?string` (null = "use global default"). Consumed by Task 4 and by Task 6 / Task 7's executor wiring via `Step` DTO reads.

- [ ] **Step 1: Write the failing test**

Append to `tests/Parser/ParserTest.php` (namespace already `Alama\LaravelArazzo\Tests\Parser`, `SymfonyYamlDecoder` + `Parser` already imported):

```php
it('parses x-idempotency-key boolean from a step', function (): void {
    $yaml = <<<'YAML'
    arazzo: 1.0.0
    info: { title: "Test", version: "1.0.0" }
    sourceDescriptions: []
    workflows:
      - workflowId: test
        steps:
          - stepId: step1
            operationId: op1
            x-idempotency-key: true
          - stepId: step2
            operationId: op2
            x-idempotency-key: false
          - stepId: step3
            operationId: op3
    YAML;

    $decoder = new SymfonyYamlDecoder();
    $raw = new RawDocument($decoder->decode($yaml), 'memory://test', Format::Yaml);
    $document = (new Parser())->parse($raw);
    $steps = $document->workflows[0]->steps;

    expect($steps[0]->idempotencyKey)->toBeTrue();
    expect($steps[1]->idempotencyKey)->toBeFalse();
    expect($steps[2]->idempotencyKey)->toBeNull();
});

it('parses x-idempotency-header string from a step', function (): void {
    $yaml = <<<'YAML'
    arazzo: 1.0.0
    info: { title: "Test", version: "1.0.0" }
    sourceDescriptions: []
    workflows:
      - workflowId: test
        steps:
          - stepId: step1
            operationId: op1
            x-idempotency-header: X-Adyen-Idempotency-Key
          - stepId: step2
            operationId: op2
    YAML;

    $decoder = new SymfonyYamlDecoder();
    $raw = new RawDocument($decoder->decode($yaml), 'memory://test', Format::Yaml);
    $document = (new Parser())->parse($raw);
    $steps = $document->workflows[0]->steps;

    expect($steps[0]->idempotencyHeader)->toBe('X-Adyen-Idempotency-Key');
    expect($steps[1]->idempotencyHeader)->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Parser/ParserTest.php`
Expected: FAIL — `Step::$idempotencyKey` / `Step::$idempotencyHeader` don't exist yet (undefined property access).

- [ ] **Step 3: Add the fields to `Step`**

In `src/Dto/Step.php`, append two new nullable defaulted parameters after `strictValidation`:

```php
        public ?string $action = null,
        public ?string $channelPath = null,
        public ?Expression $correlationId = null,
        public readonly ?bool $strictValidation = null,
        public readonly ?bool $idempotencyKey = null,
        public readonly ?string $idempotencyHeader = null,
    ) {
    }
}
```

- [ ] **Step 4: Parse the extensions in `Parser::parseStep()`**

In `src/Parser/Parser.php`, next to the existing `$strictValidation` line, add:

```php
        $strictValidation = $this->optionalBool($obj, 'x-strict-validation', $ctx);
        $idempotencyKey = $this->optionalBool($obj, 'x-idempotency-key', $ctx);
        $idempotencyHeader = $this->optionalString($obj, 'x-idempotency-header', $ctx);
```

And extend the `new Step(...)` call at the bottom of `parseStep()` with two more named args (after `strictValidation`):

```php
            strictValidation: $strictValidation,
            idempotencyKey: $idempotencyKey,
            idempotencyHeader: $idempotencyHeader,
        );
```

- [ ] **Step 5: Run parser tests to verify they pass**

Run: `vendor/bin/pest tests/Parser/ParserTest.php`
Expected: PASS (existing 1 test + 2 new tests).

- [ ] **Step 6: Run full suite to confirm no regressions**

Run: `vendor/bin/pest`
Expected: PASS — appending defaulted parameters to `Step`'s constructor doesn't break any existing positional call site.

- [ ] **Step 7: Commit**

```bash
git add src/Dto/Step.php src/Parser/Parser.php tests/Parser/ParserTest.php
git commit -m "$(cat <<'EOF'
feat(task-1): add idempotencyKey and idempotencyHeader fields to Step DTO plus x-idempotency-key / x-idempotency-header parsing

Two new nullable optional Step DTO fields (null = use global default),
parsed via optionalBool/optionalString so non-bool / non-string extension
values raise typed ParserException at parse time.

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Config flag

**Files:**
- Modify: `config/arazzo.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `config('arazzo.idempotency.enabled'): bool` (default `false`), `config('arazzo.idempotency.header'): string` (default `'Idempotency-Key'`). Consumed by Task 7 (service provider) to construct the injector singleton.

- [ ] **Step 1: Add the nested config map**

In `config/arazzo.php`, add a new top-level key after `'strict_schema_validation'`:

```php
    /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
    'strict_schema_validation' => env('ARAZZO_STRICT_SCHEMA_VALIDATION', false),

    'idempotency' => [
        /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
        'enabled' => env('ARAZZO_IDEMPOTENCY_ENABLED', false),
        /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
        'header' => env('ARAZZO_IDEMPOTENCY_HEADER', 'Idempotency-Key'),
    ],
```

- [ ] **Step 2: Run the full suite to confirm no regressions**

Run: `vendor/bin/pest`
Expected: PASS — adding a config key nobody reads yet is inert.

- [ ] **Step 3: Commit**

```bash
git add config/arazzo.php
git commit -m "$(cat <<'EOF'
feat(task-2): add arazzo.idempotency.enabled / .header config map

Global defaults for the idempotency-key injection feature, overrideable
via ARAZZO_IDEMPOTENCY_ENABLED and ARAZZO_IDEMPOTENCY_HEADER env vars.

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: `InjectionResult` value object

**Files:**
- Create: `src/Execution/InjectionResult.php`

**Interfaces:**
- Consumes: `Psr\Http\Message\RequestInterface`.
- Produces: `InjectionResult` — a `final readonly` value carrying `RequestInterface $request`, `?string $key`, `?string $header`. Consumed by Task 4 (`IdempotencyKeyInjector::inject()` returns this), by Task 5 (`StepExecutor` reads `->request`), and by Task 6 (`HttpStepExecutor` reads all three).

- [ ] **Step 1: Create the file**

`src/Execution/InjectionResult.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Psr\Http\Message\RequestInterface;

final readonly class InjectionResult
{
    public function __construct(
        public RequestInterface $request,
        public ?string $key = null,
        public ?string $header = null,
    ) {
    }
}
```

- [ ] **Step 2: Run the full suite to confirm no regressions**

Run: `vendor/bin/pest`
Expected: PASS — new unused file is inert.

- [ ] **Step 3: Commit**

```bash
git add src/Execution/InjectionResult.php
git commit -m "$(cat <<'EOF'
feat(task-3): add InjectionResult value object

Small final readonly carrier for the IdempotencyKeyInjector's output:
mutated PSR-7 request plus (when injected) the key and header name.
Mirrors StepExecutionOutcome's value-object convention.

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: `IdempotencyKeyInjector` — enable check + method filter (skeleton no-op)

**Files:**
- Create: `src/Execution/IdempotencyKeyInjector.php`
- Test: `tests/Execution/IdempotencyKeyInjectorTest.php`

**Interfaces:**
- Consumes: `Step`, `WorkflowContext`, `Psr\Http\Message\RequestInterface`, `InjectionResult` (from Task 3).
- Produces: `IdempotencyKeyInjector::inject(RequestInterface $request, Step $step, WorkflowContext $context): InjectionResult` — this task lands only the disabled/non-mutating branches (returns `new InjectionResult($request)` with `key/header` both null); key computation lands in Task 5.

- [ ] **Step 1: Write the failing tests**

Create `tests/Execution/IdempotencyKeyInjectorTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\IdempotencyKeyInjector;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use GuzzleHttp\Psr7\Request;

function idempotencyStep(?bool $idempotencyKey = null, ?string $idempotencyHeader = null): Step
{
    return new Step(
        stepId: 'step-a',
        description: null,
        operationId: 'op',
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
        idempotencyKey: $idempotencyKey,
        idempotencyHeader: $idempotencyHeader,
    );
}

function idempotencyContext(): WorkflowContext
{
    return new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1');
}

it('returns the request unchanged when the feature is disabled globally with no step override', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: false, headerDefault: 'Idempotency-Key');
    $request = new Request('POST', 'https://api.example.com/charges', [], '{"amount":100}');

    $result = $injector->inject($request, idempotencyStep(), idempotencyContext());

    expect($result->request)->toBe($request);
    expect($result->key)->toBeNull();
    expect($result->header)->toBeNull();
});

it('returns the request unchanged when the step opts out even if the global default is on', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $request = new Request('POST', 'https://api.example.com/charges', [], '{"amount":100}');

    $result = $injector->inject($request, idempotencyStep(idempotencyKey: false), idempotencyContext());

    expect($result->request)->toBe($request);
    expect($result->key)->toBeNull();
    expect($result->header)->toBeNull();
});

it('returns the request unchanged for non-mutating methods even when enabled', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');

    foreach (['GET', 'HEAD', 'OPTIONS', 'PUT', 'TRACE'] as $method) {
        $request = new Request($method, 'https://api.example.com/charges');
        $result = $injector->inject($request, idempotencyStep(), idempotencyContext());

        expect($result->request)->toBe($request);
        expect($result->key)->toBeNull();
        expect($result->header)->toBeNull();
    }
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/IdempotencyKeyInjectorTest.php`
Expected: FAIL — `IdempotencyKeyInjector` class doesn't exist.

- [ ] **Step 3: Create the injector skeleton**

`src/Execution/IdempotencyKeyInjector.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Step;
use Psr\Http\Message\RequestInterface;

final class IdempotencyKeyInjector
{
    private const MUTATING_METHODS = ['POST', 'PATCH', 'DELETE'];

    public function __construct(
        private bool $enabledDefault,
        private string $headerDefault,
    ) {
    }

    public function inject(RequestInterface $request, Step $step, WorkflowContext $context): InjectionResult
    {
        $enabled = $step->idempotencyKey ?? $this->enabledDefault;
        if (!$enabled) {
            return new InjectionResult($request);
        }

        if (!in_array(strtoupper($request->getMethod()), self::MUTATING_METHODS, true)) {
            return new InjectionResult($request);
        }

        // Task 5 lands key computation here. For now, still a no-op so the enable/method tests pass.
        return new InjectionResult($request);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/IdempotencyKeyInjectorTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Run full suite to confirm no regressions**

Run: `vendor/bin/pest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Execution/IdempotencyKeyInjector.php tests/Execution/IdempotencyKeyInjectorTest.php
git commit -m "$(cat <<'EOF'
feat(task-4): add IdempotencyKeyInjector skeleton with enable/method filter

Injector returns an unchanged InjectionResult when the feature is
disabled globally without a step-level override, when the step
explicitly opts out, or when the request method is not POST/PATCH/DELETE.
Key computation lands in the next task.

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: `IdempotencyKeyInjector` — deterministic key computation

**Files:**
- Modify: `src/Execution/IdempotencyKeyInjector.php`
- Modify: `tests/Execution/IdempotencyKeyInjectorTest.php`

**Interfaces:**
- Consumes: `Step`, `WorkflowContext`, `RequestInterface`, `InjectionResult`.
- Produces: `IdempotencyKeyInjector::inject()` now returns a `InjectionResult` with a non-null `key` (64-char hex SHA-256) and `header` when enabled and method is mutating. Consumed by Task 6 and Task 7 executor wiring.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Execution/IdempotencyKeyInjectorTest.php`:

```php
it('injects a deterministic key and the default header on POST/PATCH/DELETE when enabled', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');

    foreach (['POST', 'PATCH', 'DELETE'] as $method) {
        $request = new Request($method, 'https://api.example.com/charges', [], '{"amount":100}');

        $result = $injector->inject($request, idempotencyStep(), idempotencyContext());

        expect($result->key)->not->toBeNull();
        expect($result->key)->toMatch('/^[0-9a-f]{64}$/');
        expect($result->header)->toBe('Idempotency-Key');
        expect($result->request->getHeaderLine('Idempotency-Key'))->toBe($result->key);
    }
});

it('produces the same key across two calls with the same request identity and payload', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $request = new Request('POST', 'https://api.example.com/charges', [], '{"amount":100}');

    $a = $injector->inject($request, idempotencyStep(), idempotencyContext());
    $b = $injector->inject($request, idempotencyStep(), idempotencyContext());

    expect($a->key)->toBe($b->key);
});

it('produces different keys when the body changes', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $a = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"a":1}'), idempotencyStep(), idempotencyContext());
    $b = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"a":2}'), idempotencyStep(), idempotencyContext());

    expect($a->key)->not->toBe($b->key);
});

it('produces different keys when the URI changes', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $a = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"a":1}'), idempotencyStep(), idempotencyContext());
    $b = $injector->inject(new Request('POST', 'https://api.example.com/y', [], '{"a":1}'), idempotencyStep(), idempotencyContext());

    expect($a->key)->not->toBe($b->key);
});

it('produces different keys when the stepId changes', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $ctx = idempotencyContext();
    $request = new Request('POST', 'https://api.example.com/x', [], '{"a":1}');

    $a = $injector->inject($request, idempotencyStep(), $ctx);
    $stepB = new Step(
        stepId: 'step-b',
        description: null, operationId: 'op', operationPath: null, workflowId: null,
        parameters: [], requestBody: null, successCriteria: [], onSuccess: [], onFailure: [], outputs: [],
    );
    $b = $injector->inject($request, $stepB, $ctx);

    expect($a->key)->not->toBe($b->key);
});

it('produces different keys when the definitionId or workflowId changes', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $request = new Request('POST', 'https://api.example.com/x', [], '{"a":1}');

    $a = $injector->inject($request, idempotencyStep(), new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1'));
    $b = $injector->inject($request, idempotencyStep(), new WorkflowContext('def-2', [], [], [], 'wf-1', 'exec-1'));
    $c = $injector->inject($request, idempotencyStep(), new WorkflowContext('def-1', [], [], [], 'wf-2', 'exec-1'));

    expect($a->key)->not->toBe($b->key);
    expect($a->key)->not->toBe($c->key);
});

it('produces the same key for two JSON bodies that differ only in top-level key order', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');

    $a = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"a":1,"b":2}'), idempotencyStep(), idempotencyContext());
    $b = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"b":2,"a":1}'), idempotencyStep(), idempotencyContext());

    expect($a->key)->toBe($b->key);
});

it('produces the same key for JSON bodies with nested-object key reordering', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');

    $a = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"outer":{"a":1,"b":2}}'), idempotencyStep(), idempotencyContext());
    $b = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"outer":{"b":2,"a":1}}'), idempotencyStep(), idempotencyContext());

    expect($a->key)->toBe($b->key);
});

it('does NOT reorder list arrays (positional semantics preserved)', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');

    $a = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"items":[1,2,3]}'), idempotencyStep(), idempotencyContext());
    $b = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"items":[3,2,1]}'), idempotencyStep(), idempotencyContext());

    expect($a->key)->not->toBe($b->key);
});

it('falls back to raw bytes for a non-JSON body and still produces a deterministic key', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');

    $a = $injector->inject(new Request('POST', 'https://api.example.com/x', [], 'raw-non-json-body'), idempotencyStep(), idempotencyContext());
    $b = $injector->inject(new Request('POST', 'https://api.example.com/x', [], 'raw-non-json-body'), idempotencyStep(), idempotencyContext());
    $c = $injector->inject(new Request('POST', 'https://api.example.com/x', [], 'different-raw-body'), idempotencyStep(), idempotencyContext());

    expect($a->key)->toBe($b->key);
    expect($a->key)->not->toBe($c->key);
});

it('uses the step-level x-idempotency-header override when set', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $request = new Request('POST', 'https://api.example.com/x', [], '{"a":1}');

    $result = $injector->inject($request, idempotencyStep(idempotencyHeader: 'X-Adyen-Idempotency-Key'), idempotencyContext());

    expect($result->header)->toBe('X-Adyen-Idempotency-Key');
    expect($result->request->getHeaderLine('X-Adyen-Idempotency-Key'))->toBe($result->key);
    expect($result->request->getHeaderLine('Idempotency-Key'))->toBe('');
});

it('overwrites the configured header if the request already carries it', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $request = new Request('POST', 'https://api.example.com/x', ['Idempotency-Key' => 'manual-value'], '{"a":1}');

    $result = $injector->inject($request, idempotencyStep(), idempotencyContext());

    expect($result->key)->not->toBe('manual-value');
    expect($result->request->getHeaderLine('Idempotency-Key'))->toBe($result->key);
});

it('rewinds the request body stream after reading so downstream sending sees an unread body', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $request = new Request('POST', 'https://api.example.com/x', [], '{"a":1}');

    $result = $injector->inject($request, idempotencyStep(), idempotencyContext());

    expect((string) $result->request->getBody())->toBe('{"a":1}');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/IdempotencyKeyInjectorTest.php`
Expected: FAIL — key is still `null` on the currently-skeleton implementation.

- [ ] **Step 3: Implement key computation**

Replace the body of `IdempotencyKeyInjector::inject()` in `src/Execution/IdempotencyKeyInjector.php`:

```php
    public function inject(RequestInterface $request, Step $step, WorkflowContext $context): InjectionResult
    {
        $enabled = $step->idempotencyKey ?? $this->enabledDefault;
        if (!$enabled) {
            return new InjectionResult($request);
        }

        if (!in_array(strtoupper($request->getMethod()), self::MUTATING_METHODS, true)) {
            return new InjectionResult($request);
        }

        $fingerprint = $this->requestFingerprint($request);

        $key = hash('sha256', implode('|', [
            (string) $context->getDefinitionId(),
            (string) $context->getWorkflowId(),
            $step->stepId,
            $fingerprint,
        ]));

        $header = $step->idempotencyHeader ?? $this->headerDefault;

        return new InjectionResult(
            request: $request->withHeader($header, $key),
            key: $key,
            header: $header,
        );
    }

    private function requestFingerprint(RequestInterface $request): string
    {
        $body = $request->getBody();
        $body->rewind();
        $raw = $body->getContents();
        $body->rewind();

        $canonical = $this->canonicalizeBody($raw);

        return hash('sha256', implode('|', [
            strtoupper($request->getMethod()),
            (string) $request->getUri(),
            $canonical,
        ]));
    }

    private function canonicalizeBody(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $raw;
        }

        $sorted = $this->recursivelySortAssociativeKeys($decoded);

        $reEncoded = json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $reEncoded === false ? $raw : $reEncoded;
    }

    /**
     * @param array<int|string,mixed> $value
     * @return array<int|string,mixed>
     */
    private function recursivelySortAssociativeKeys(array $value): array
    {
        // Preserve positional semantics of list arrays; only sort key order on associative arrays.
        $isList = array_is_list($value);

        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->recursivelySortAssociativeKeys($v);
            }
        }

        if (!$isList) {
            ksort($value);
        }

        return $value;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/IdempotencyKeyInjectorTest.php`
Expected: PASS (all 15 tests — 3 from Task 4 + 12 new).

- [ ] **Step 5: Run full suite to confirm no regressions**

Run: `vendor/bin/pest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Execution/IdempotencyKeyInjector.php tests/Execution/IdempotencyKeyInjectorTest.php
git commit -m "$(cat <<'EOF'
feat(task-5): implement deterministic key computation in IdempotencyKeyInjector

Key = sha256(definitionId | workflowId | stepId | fingerprint) where
fingerprint = sha256(METHOD | URI | canonicalized-body). Canonicalization
recursively sorts associative-array keys (list arrays preserve positional
order) so JSON payloads whose only difference is object-key order still
produce the same key. Non-JSON bodies fall back to raw bytes. Body
stream is rewound after read so downstream sending sees an unread body.

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: Wire injector into `StepExecutor` (sync path)

**Files:**
- Modify: `src/Execution/StepExecutor.php`
- Modify: `tests/Execution/StepExecutorTest.php`

**Interfaces:**
- Consumes: `IdempotencyKeyInjector` (Task 4/5), `InjectionResult` (Task 3), `Step` extensions (Task 1).
- Produces: `StepExecutor::__construct(ClientInterface, ExpressionResolverInterface, bool $strictValidationDefault = false, ?IdempotencyKeyInjector $injector = null)` — new optional trailing constructor param. `execute()` mutates the compiled request via the injector before parsing headers into context (so injected header is captured in the persisted `withStepRequest` snapshot).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Execution/StepExecutorTest.php`:

```php
use Alama\LaravelArazzo\Execution\IdempotencyKeyInjector;

it('injects the Idempotency-Key header into the request when the injector is enabled', function (): void {
    $resolver = Mockery::mock(ExpressionResolverInterface::class);
    $resolver->shouldReceive('compileRequest')->andReturn(new Request('POST', 'https://api.example.com/x', [], '{"a":1}'));
    $resolver->shouldReceive('extractOutputs')->andReturn([]);
    $resolver->shouldReceive('evaluateSuccessCriteria')->andReturn(true);

    $capturedRequest = null;
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('sendRequest')->andReturnUsing(function ($request) use (&$capturedRequest) {
        $capturedRequest = $request;
        return new Response(200, [], '{}');
    });

    $executor = new StepExecutor(
        httpClient: $client,
        expressionResolver: $resolver,
        strictValidationDefault: false,
        injector: new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key'),
    );
    $step = new Step('test-step', null, 'op', null, null, [], null, [], [], [], []);
    $document = new ArazzoDocument('1.0.0', new Info('t', null, null, '1'), [], [], new Components([], [], [], []), []);

    [$context] = $executor->execute($step, new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1'), $document);

    expect($capturedRequest->getHeaderLine('Idempotency-Key'))->toMatch('/^[0-9a-f]{64}$/');
    expect($context->getSteps()['test-step']['request']['headers']['Idempotency-Key'] ?? null)
        ->toMatch('/^[0-9a-f]{64}$/');
});

it('does not inject a header when no injector is passed', function (): void {
    $resolver = Mockery::mock(ExpressionResolverInterface::class);
    $resolver->shouldReceive('compileRequest')->andReturn(new Request('POST', 'https://api.example.com/x', [], '{"a":1}'));
    $resolver->shouldReceive('extractOutputs')->andReturn([]);
    $resolver->shouldReceive('evaluateSuccessCriteria')->andReturn(true);

    $capturedRequest = null;
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('sendRequest')->andReturnUsing(function ($request) use (&$capturedRequest) {
        $capturedRequest = $request;
        return new Response(200, [], '{}');
    });

    $executor = new StepExecutor($client, $resolver);
    $step = new Step('test-step', null, 'op', null, null, [], null, [], [], [], []);
    $document = new ArazzoDocument('1.0.0', new Info('t', null, null, '1'), [], [], new Components([], [], [], []), []);

    $executor->execute($step, new WorkflowContext('def-1'), $document);

    expect($capturedRequest->getHeaderLine('Idempotency-Key'))->toBe('');
});

it('does not inject a header on non-mutating verbs even when the injector is enabled', function (): void {
    $resolver = Mockery::mock(ExpressionResolverInterface::class);
    $resolver->shouldReceive('compileRequest')->andReturn(new Request('GET', 'https://api.example.com/x'));
    $resolver->shouldReceive('extractOutputs')->andReturn([]);
    $resolver->shouldReceive('evaluateSuccessCriteria')->andReturn(true);

    $capturedRequest = null;
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('sendRequest')->andReturnUsing(function ($request) use (&$capturedRequest) {
        $capturedRequest = $request;
        return new Response(200, [], '{}');
    });

    $executor = new StepExecutor(
        httpClient: $client,
        expressionResolver: $resolver,
        strictValidationDefault: false,
        injector: new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key'),
    );
    $step = new Step('test-step', null, 'op', null, null, [], null, [], [], [], []);
    $document = new ArazzoDocument('1.0.0', new Info('t', null, null, '1'), [], [], new Components([], [], [], []), []);

    $executor->execute($step, new WorkflowContext('def-1'), $document);

    expect($capturedRequest->getHeaderLine('Idempotency-Key'))->toBe('');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/StepExecutorTest.php`
Expected: FAIL — constructor doesn't accept an `injector` argument (unknown named parameter).

- [ ] **Step 3: Extend `StepExecutor`**

In `src/Execution/StepExecutor.php`, extend the constructor and inject the mutated request BEFORE the existing body/query/header parsing loop:

```php
use Alama\LaravelArazzo\Execution\IdempotencyKeyInjector;

// ...

class StepExecutor
{
    public function __construct(
        private ClientInterface $httpClient,
        private ExpressionResolverInterface $expressionResolver,
        private bool $strictValidationDefault = false,
        private ?IdempotencyKeyInjector $injector = null,
    ) {
    }

    // ... existing shouldValidateSchema() unchanged ...

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document): array
    {
        // 1. Compile Request
        $request = $this->expressionResolver->compileRequest($step, $context, $document);

        // 1a. Idempotency-Key injection (before header/body snapshot so it lands in $context->withStepRequest)
        if ($this->injector !== null) {
            $request = $this->injector->inject($request, $step, $context)->request;
        }

        // Parse body back to array for context storage
        // ... (existing code from `$bodyStream = $request->getBody();` onward unchanged) ...
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/StepExecutorTest.php`
Expected: PASS (existing 2 tests + 3 new = 5).

- [ ] **Step 5: Run full suite to confirm no regressions**

Run: `vendor/bin/pest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Execution/StepExecutor.php tests/Execution/StepExecutorTest.php
git commit -m "$(cat <<'EOF'
feat(task-6): wire IdempotencyKeyInjector into StepExecutor sync path

Optional trailing constructor param (nullable so existing test call
sites keep compiling). When an injector is provided, StepExecutor
runs it after compileRequest() and before the header snapshot, so the
injected Idempotency-Key appears in the persisted context.

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 7: Wire injector into `HttpStepExecutor` (async path) + emit ledger event

**Files:**
- Modify: `src/Execution/HttpStepExecutor.php`
- Modify: `tests/Execution/HttpStepExecutorTest.php`

**Interfaces:**
- Consumes: `IdempotencyKeyInjector` (Task 4/5), `InjectionResult` (Task 3), `EventLedgerInterface` (already in `src/Execution/Contracts/`), `Step` extensions (Task 1).
- Produces: `HttpStepExecutor::__construct(HttpClientInterface, ExpressionResolverInterface, bool $strictValidationDefault = false, ?IdempotencyKeyInjector $injector = null, ?EventLedgerInterface $eventLedger = null)` — two new optional trailing params. `execute()` emits `step.idempotency_key_injected` on the ledger when injection produces a non-null key.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Execution/HttpStepExecutorTest.php`:

```php
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\IdempotencyKeyInjector;

it('injects the Idempotency-Key header and emits a ledger event on POST when enabled', function (): void {
    $client = new class (new Response(200, [], '{}')) implements \Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface {
        public ?\Psr\Http\Message\RequestInterface $captured = null;

        public function __construct(private \Psr\Http\Message\ResponseInterface $response)
        {
        }

        public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
        {
            $this->captured = $request;
            return $this->response;
        }
    };

    $ledger = Mockery::mock(EventLedgerInterface::class);
    $ledger->shouldReceive('append')->once()->with(
        'exec-1',
        'step.idempotency_key_injected',
        Mockery::on(function ($payload) {
            return $payload['stepId'] === 's1'
                && $payload['header'] === 'Idempotency-Key'
                && is_string($payload['key'])
                && preg_match('/^[0-9a-f]{64}$/', $payload['key']) === 1;
        }),
    );

    $resolver = new class extends HttpStepExecutorMockResolver {
        public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
        {
            return new Request('POST', 'https://api.example.com/charges', [], '{"amount":100}');
        }
    };

    $executor = new HttpStepExecutor(
        httpClient: $client,
        expressionResolver: $resolver,
        strictValidationDefault: false,
        injector: new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key'),
        eventLedger: $ledger,
    );
    $step = new Step('s1', null, null, null, null, [], null, [], [], [], []);

    $executor->execute($step, new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1'), httpStepExecutorDocument(), 'exec-1');

    expect($client->captured->getHeaderLine('Idempotency-Key'))->toMatch('/^[0-9a-f]{64}$/');
});

it('does not emit a ledger event on non-mutating verbs', function (): void {
    $ledger = Mockery::mock(EventLedgerInterface::class);
    $ledger->shouldNotReceive('append');

    $executor = new HttpStepExecutor(
        httpClient: new HttpStepExecutorMockClient(new Response(200, [], '{}')),
        expressionResolver: new HttpStepExecutorMockResolver(), // compileRequest returns GET by default
        strictValidationDefault: false,
        injector: new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key'),
        eventLedger: $ledger,
    );
    $step = new Step('s1', null, null, null, null, [], null, [], [], [], []);

    $executor->execute($step, new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1'), httpStepExecutorDocument(), 'exec-1');
});

it('does not emit a ledger event when injector is not passed', function (): void {
    $ledger = Mockery::mock(EventLedgerInterface::class);
    $ledger->shouldNotReceive('append');

    $executor = new HttpStepExecutor(
        httpClient: new HttpStepExecutorMockClient(new Response(200, [], '{}')),
        expressionResolver: new HttpStepExecutorMockResolver(),
        strictValidationDefault: false,
        injector: null,
        eventLedger: $ledger,
    );
    $step = new Step('s1', null, null, null, null, [], null, [], [], [], []);

    $executor->execute($step, new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1'), httpStepExecutorDocument(), 'exec-1');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/HttpStepExecutorTest.php`
Expected: FAIL — constructor doesn't accept `injector` / `eventLedger` named args.

- [ ] **Step 3: Extend `HttpStepExecutor`**

In `src/Execution/HttpStepExecutor.php`, replace the class contents to add the two new constructor params and inject-then-emit before the send:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\StepProtocolExecutorInterface;

final class HttpStepExecutor implements StepProtocolExecutorInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ExpressionResolverInterface $expressionResolver,
        private bool $strictValidationDefault = false,
        private ?IdempotencyKeyInjector $injector = null,
        private ?EventLedgerInterface $eventLedger = null,
    ) {
    }

    private function shouldValidateSchema(Step $step): bool
    {
        return $step->strictValidation ?? $this->strictValidationDefault;
    }

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return $step->action === null;
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        $request = $this->expressionResolver->compileRequest($step, $context, $document);

        if ($this->injector !== null) {
            $result = $this->injector->inject($request, $step, $context);
            $request = $result->request;
            if ($result->key !== null && $this->eventLedger !== null) {
                $this->eventLedger->append($executionId, 'step.idempotency_key_injected', [
                    'stepId' => $step->stepId,
                    'header' => $result->header,
                    'key' => $result->key,
                ]);
            }
        }

        $response = $this->httpClient->sendRequest($request);

        $decodedBody = json_decode((string) $response->getBody(), true);
        $body = is_array($decodedBody) ? $decodedBody : [];

        if ($this->shouldValidateSchema($step)) {
            $this->expressionResolver->validateResponseSchema(
                $step,
                $response->getStatusCode(),
                $response->getHeaderLine('Content-Type'),
                $body,
                $document,
            );
        }

        $contextWithResponse = $context->withStepResponse($step->stepId, [
            'statusCode' => $response->getStatusCode(),
            'body' => $body,
        ]);

        $outputs = $this->expressionResolver->extractOutputs($step, $contextWithResponse, $document);

        return StepExecutionOutcome::resolved($response->getStatusCode(), $outputs, $body);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/HttpStepExecutorTest.php`
Expected: PASS (existing tests + 3 new).

- [ ] **Step 5: Run full suite to confirm no regressions**

Run: `vendor/bin/pest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Execution/HttpStepExecutor.php tests/Execution/HttpStepExecutorTest.php
git commit -m "$(cat <<'EOF'
feat(task-7): wire IdempotencyKeyInjector into HttpStepExecutor async path

Two new optional trailing constructor params (injector + eventLedger).
When both are present and injection produces a non-null key,
HttpStepExecutor emits a `step.idempotency_key_injected` event through
the ledger carrying stepId, header, and key.

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 8: ServiceProvider binding + config-wiring test

**Files:**
- Modify: `src/LaravelArazzoServiceProvider.php`
- Modify: `tests/LaravelArazzoServiceProviderBindingsTest.php`

**Interfaces:**
- Consumes: `IdempotencyKeyInjector` (Task 4/5), config keys (Task 2), `EventLedgerInterface` (existing binding).
- Produces: container binding for `IdempotencyKeyInjector::class` as a singleton constructed from config; extended `StepExecutor::class` and `HttpStepExecutor::class` bindings that receive the injector (and, for the async executor, the ledger).

- [ ] **Step 1: Write the failing tests**

Append to `tests/LaravelArazzoServiceProviderBindingsTest.php`:

```php
use Alama\LaravelArazzo\Execution\IdempotencyKeyInjector;

it('binds IdempotencyKeyInjector as a singleton', function () {
    $a = app(IdempotencyKeyInjector::class);
    $b = app(IdempotencyKeyInjector::class);

    expect($a)->toBeInstanceOf(IdempotencyKeyInjector::class);
    expect($a)->toBe($b);
});

it('constructs IdempotencyKeyInjector from config so header override + enabled=true drive injection', function () {
    config()->set('arazzo.idempotency.enabled', true);
    config()->set('arazzo.idempotency.header', 'X-Custom-Idempotency-Key');

    // Re-resolve the singleton with the new config.
    app()->forgetInstance(IdempotencyKeyInjector::class);
    $injector = app(IdempotencyKeyInjector::class);

    $step = new \Alama\LaravelArazzo\Dto\Step('s1', null, 'op', null, null, [], null, [], [], [], []);
    $context = new \Alama\LaravelArazzo\Execution\WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1');
    $request = new \GuzzleHttp\Psr7\Request('POST', 'https://api.example.com/x', [], '{"a":1}');

    $result = $injector->inject($request, $step, $context);

    expect($result->header)->toBe('X-Custom-Idempotency-Key');
    expect($result->key)->toMatch('/^[0-9a-f]{64}$/');
    expect($result->request->getHeaderLine('X-Custom-Idempotency-Key'))->toBe($result->key);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/LaravelArazzoServiceProviderBindingsTest.php`
Expected: FAIL — `IdempotencyKeyInjector::class` has no container binding.

- [ ] **Step 3: Add the singleton binding + rewire executor bindings**

In `src/LaravelArazzoServiceProvider.php`, add the new singleton binding above the `StepExecutor::class` binding (~ line 129):

```php
        $this->app->singleton(IdempotencyKeyInjector::class, function () {
            return new IdempotencyKeyInjector(
                enabledDefault: (bool) config('arazzo.idempotency.enabled', false),
                headerDefault: (string) config('arazzo.idempotency.header', 'Idempotency-Key'),
            );
        });

        $this->app->singleton(StepExecutor::class, function ($app) {
            return new StepExecutor(
                $app->make(ClientInterface::class),
                $app->make(ExpressionResolverInterface::class),
                (bool) config('arazzo.strict_schema_validation', false),
                $app->make(IdempotencyKeyInjector::class),
            );
        });
```

And extend the `HttpStepExecutor::class` binding (~ line 206):

```php
        $this->app->singleton(HttpStepExecutor::class, function ($app) {
            return new HttpStepExecutor(
                $app->make(HttpClientInterface::class),
                $app->make(ExpressionResolverInterface::class),
                (bool) config('arazzo.strict_schema_validation', false),
                $app->make(IdempotencyKeyInjector::class),
                $app->make(EventLedgerInterface::class),
            );
        });
```

Add the `use` import at the top of the service provider file if it's not already present:

```php
use Alama\LaravelArazzo\Execution\IdempotencyKeyInjector;
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/LaravelArazzoServiceProviderBindingsTest.php`
Expected: PASS (existing tests + 2 new).

- [ ] **Step 5: Run full suite to confirm no regressions**

Run: `vendor/bin/pest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/LaravelArazzoServiceProvider.php tests/LaravelArazzoServiceProviderBindingsTest.php
git commit -m "$(cat <<'EOF'
feat(task-8): bind IdempotencyKeyInjector as singleton and wire into executors

New singleton binding constructs the injector from
config('arazzo.idempotency.enabled') and
config('arazzo.idempotency.header'). StepExecutor and HttpStepExecutor
bindings extended to receive the injector; HttpStepExecutor also gets
the EventLedgerInterface for the step.idempotency_key_injected event.

Closes the 05-idempotency-replay-safeguards feature.

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Post-implementation cleanup

- [ ] Manual: update `docs/superpowers/roadmap/05-idempotency-replay-safeguards.md` `Status:` line from `Not started — needs brainstorming` to `Implemented — see docs/superpowers/plans/2026-07-24-idempotency-replay-safeguards.md and CHANGELOG.md`.
- [ ] Manual: add a CHANGELOG entry for the feature.
- [ ] Manual: if strict PHPStan clean-up is desired, run `vendor/bin/phpstan analyse --memory-limit=1G src/Execution/IdempotencyKeyInjector.php src/Execution/InjectionResult.php src/Execution/StepExecutor.php src/Execution/HttpStepExecutor.php` and address any new findings.
