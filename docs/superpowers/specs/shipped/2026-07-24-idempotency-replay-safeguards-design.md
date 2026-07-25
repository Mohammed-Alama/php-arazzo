# Idempotency & Replay Safeguards — Design

Roadmap seed: [docs/superpowers/roadmap/05-idempotency-replay-safeguards.md](../roadmap/05-idempotency-replay-safeguards.md).
Depends on: [03 — Native Async Control Flow](../roadmap/03-native-async-control-flow.md) (landed —
`StepProtocolExecutorInterface`, `HttpStepExecutor`, `StepExecutionWorker`, `EventLedgerInterface`
are all real, wired, and drive both the sync (`StepExecutor`) and async (`HttpStepExecutor`)
execution paths).

## Starting point

Neither `StepExecutor::execute()` nor `HttpStepExecutor::execute()` attaches any dedup metadata
to the outgoing PSR-7 request today. If a step's HTTP call times out mid-flight and is retried —
either automatically by Laravel's queue worker on the async path, or manually by an operator re-
running an execution on the sync path — the upstream API has no way to know the second request is
"the same as the first" rather than a genuinely new operation. Payment/booking/order APIs that
implement RFC-draft `Idempotency-Key` semantics rely on the caller to send a stable key; without
one, replays risk double-charging or double-booking. This item fills that gap: the engine
auto-generates a deterministic key per (definition, workflow, step, compiled request payload)
and injects it as an HTTP header on mutating verbs, opt-in via config and per-step extension.

## Scope

**In scope:**
- A new `IdempotencyKeyInjector` (stateless) that computes a stable key and returns a mutated
  PSR-7 request with the configured header set.
- Key formula:
  `sha256(definitionId + '|' + workflowId + '|' + stepId + '|' + requestFingerprint)` where
  `requestFingerprint = sha256(method + '|' + uri + '|' + canonicalizedBody)`. Deterministic
  across runs: same identity plus same compiled payload → same key.
- Method filter: `POST`, `PATCH`, `DELETE` only. Non-mutating verbs pass through unchanged.
- Opt-in config: `config('arazzo.idempotency.enabled')` default `false`. Per-step
  `x-idempotency-key: bool` extension overrides the global default.
- Configurable header name: `config('arazzo.idempotency.header', 'Idempotency-Key')` global;
  per-step `x-idempotency-header: <string>` overrides.
- Wiring into both execution paths: `StepExecutor::execute()` (sync) and
  `HttpStepExecutor::execute()` (async) call the injector immediately after `compileRequest()`
  and use the mutated request for the actual HTTP send.
- Event ledger record `step.idempotency_key_injected` (`{stepId, header, key}`) emitted from
  the async path only (`HttpStepExecutor`, which already carries an `EventLedgerInterface`
  dependency slot in its wiring context via `StepExecutionWorker`).
- New `Step` DTO fields `?bool $idempotencyKey`, `?string $idempotencyHeader`; parser reads
  `x-idempotency-key` via `optionalBool` and `x-idempotency-header` via `optionalString`.

**Out of scope (explicitly deferred):**
- Engine-side dedup ledger. A local `(executionId, stepId, key) → outcome` table that lets the
  engine itself skip a step whose key already committed successfully is a separate feature —
  bigger scope (migration, failure-mode semantics for "ledger says done but upstream never
  actually processed"), and orthogonal to injecting the header.
- Payload-fingerprint mismatch detection (RFC draft §5's "Idempotency-Fingerprint" hard-error
  semantics). Interesting but not requested; the deterministic key formula already means
  identical replays reuse the same key and different-payload replays get a fresh key.
- Non-mutating HTTP verbs (`GET`/`HEAD`/`OPTIONS`/`TRACE`): already spec-idempotent, injecting
  a key is noise. `PUT`: spec-idempotent too; deferred until a real use case demands it.
- Attempt counter in the key. Deliberately absent so replays reuse the key and upstream dedups.
- Idempotency for `AsyncApiStepExecutor` (`action: send`/`receive`). AsyncAPI messages aren't
  HTTP requests; `Idempotency-Key` header semantics don't map cleanly to pub/sub. Deferred.
- Emitting `step.idempotency_key_injected` from the sync path. `StepExecutor` has no ledger
  dependency today; adding one purely for a passive audit event would balloon its constructor
  and every one of its ~30 test-site call sites. Sync path still injects the header, just
  silently.

## Architecture

### `IdempotencyKeyInjector` (new — `src/Execution/IdempotencyKeyInjector.php`)

```php
final class IdempotencyKeyInjector
{
    private const MUTATING_METHODS = ['POST', 'PATCH', 'DELETE'];

    public function __construct(
        private bool $enabledDefault,
        private string $headerDefault,
    ) {}

    public function inject(RequestInterface $request, Step $step, WorkflowContext $context): InjectionResult;
}
```

```php
final readonly class InjectionResult
{
    public function __construct(
        public RequestInterface $request,
        public ?string $key,      // null when not injected
        public ?string $header,   // null when not injected
    ) {}
}
```

Behavior:
1. `enabled = $step->idempotencyKey ?? $this->enabledDefault`. If `false` → return
   `new InjectionResult($request, null, null)`.
2. Method check: `strtoupper($request->getMethod())` must be in
   `self::MUTATING_METHODS`. Otherwise → return unchanged (`key`, `header` both `null`).
3. **Fingerprint compute** — `sha256($method . '|' . (string) $uri . '|' . $canonicalBody)`.
   Canonicalization of body:
   - Rewind stream, read to string, rewind stream (mirror the read pattern already at
     `StepExecutor.php:39-43`).
   - `json_decode($raw, true)`. If result is an `array` and `json_last_error() === JSON_ERROR_NONE`,
     recursively `ksort` (only on associative arrays; list arrays kept as-is to preserve
     positional semantics), then re-encode with
     `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
   - Else → use raw bytes.
4. **Key compute** —
   `sha256($context->getDefinitionId() . '|' . $context->getWorkflowId() . '|' . $step->stepId . '|' . $fingerprint)`.
   Hex-encoded, 64 chars, printable-ASCII per Idempotency-Key draft §2's header syntax.
5. `header = $step->idempotencyHeader ?? $this->headerDefault`.
6. Return `new InjectionResult($request->withHeader($header, $key), $key, $header)`.

Stateless. No side effects beyond producing a new request instance (PSR-7 requests are
immutable — `withHeader()` returns a new object; original untouched).

### `Step` DTO (`src/Dto/Step.php`)

Two new nullable fields appended after `strictValidation`, preserving all existing positional
`new Step(...)` construction sites:

```php
public readonly ?bool $idempotencyKey = null,
public readonly ?string $idempotencyHeader = null,
```

### `Parser::parseStep()` (`src/Parser/Parser.php`)

Inserted next to the existing `$strictValidation` line:

```php
$idempotencyKey    = $this->optionalBool($obj, 'x-idempotency-key', $ctx);
$idempotencyHeader = $this->optionalString($obj, 'x-idempotency-header', $ctx);
```

Passed as named args to the `new Step(...)` constructor call. Non-bool / non-string values
raise typed `ParserException` at parse time via the existing helper contracts.

### Config (`config/arazzo.php`)

New nested map (grouped so the two knobs read as one feature — avoids scattering flat top-level
keys):

```php
'idempotency' => [
    /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
    'enabled' => env('ARAZZO_IDEMPOTENCY_ENABLED', false),
    /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
    'header'  => env('ARAZZO_IDEMPOTENCY_HEADER', 'Idempotency-Key'),
],
```

### `StepExecutor` wiring (sync path)

Constructor gains one dependency:
```php
public function __construct(
    private ClientInterface $httpClient,
    private ExpressionResolverInterface $expressionResolver,
    private bool $strictValidationDefault = false,
    private ?IdempotencyKeyInjector $injector = null,   // nullable so ~30 direct `new` sites still compile
) {}
```

`execute()` calls the injector right after `compileRequest()`:

```php
$request = $this->expressionResolver->compileRequest($step, $context, $document);
if ($this->injector !== null) {
    $request = $this->injector->inject($request, $step, $context)->request;
}
// ... (existing body-parse / query-parse / headers-into-context loop unchanged)
```

Nullable to keep the many existing bare-arg unit-test constructions valid without a mechanical
sweep; production binding always passes it via the service provider.

### `HttpStepExecutor` wiring (async path)

Constructor gains two dependencies:
```php
public function __construct(
    private HttpClientInterface $httpClient,
    private ExpressionResolverInterface $expressionResolver,
    private bool $strictValidationDefault = false,
    private ?IdempotencyKeyInjector $injector = null,
    private ?EventLedgerInterface $eventLedger = null,
) {}
```

`execute()`:
```php
$request = $this->expressionResolver->compileRequest($step, $context, $document);
if ($this->injector !== null) {
    $result  = $this->injector->inject($request, $step, $context);
    $request = $result->request;
    if ($result->key !== null && $this->eventLedger !== null) {
        $this->eventLedger->append($executionId, 'step.idempotency_key_injected', [
            'stepId' => $step->stepId,
            'header' => $result->header,
            'key'    => $result->key,
        ]);
    }
}
$response = $this->httpClient->sendRequest($request);
// ... (existing validate / decode / extractOutputs unchanged)
```

`$executionId` is already a parameter of the `StepProtocolExecutorInterface::execute()`
signature 03 defined — no new plumbing.

### `LaravelArazzoServiceProvider`

New singleton binding:

```php
$this->app->singleton(IdempotencyKeyInjector::class, function () {
    return new IdempotencyKeyInjector(
        (bool) config('arazzo.idempotency.enabled', false),
        (string) config('arazzo.idempotency.header', 'Idempotency-Key'),
    );
});
```

`StepExecutor::class` binding extended:
```php
new StepExecutor(
    $app->make(ClientInterface::class),
    $app->make(ExpressionResolverInterface::class),
    (bool) config('arazzo.strict_schema_validation', false),
    $app->make(IdempotencyKeyInjector::class),
);
```

`HttpStepExecutor::class` binding extended:
```php
new HttpStepExecutor(
    $app->make(HttpClientInterface::class),
    $app->make(ExpressionResolverInterface::class),
    (bool) config('arazzo.strict_schema_validation', false),
    $app->make(IdempotencyKeyInjector::class),
    $app->make(EventLedgerInterface::class),
);
```

## Data Flow

### Sync path

```
$request = $resolver->compileRequest($step, $context, $document)
$request = $injector->inject($request, $step, $context)->request   ← NEW
# body/query/headers parsed into $context->withStepRequest(...) as today
# — Idempotency-Key naturally appears in the persisted headers snapshot
$response = $httpClient->sendRequest($request)
# ... validate, extractOutputs, evaluateSuccessCriteria unchanged
```

### Async path

```
$request = $resolver->compileRequest($step, $context, $document)
$result  = $injector->inject($request, $step, $context)
$request = $result->request
if ($result->key !== null) {
    $eventLedger->append($executionId, 'step.idempotency_key_injected', [...])
}
$response = $httpClient->sendRequest($request)
# ... validate, decode, extractOutputs unchanged
```

### Replay determinism

- **Manual replay of same run:** `definitionId + workflowId + stepId` are constants of the run;
  `requestFingerprint` is stable iff the compiled request is byte-identical, which it is —
  `compileRequest()` is a pure function of `Step + WorkflowContext + ArazzoDocument` and the
  replay restores the prior context from the state store. Same key → upstream dedups.
- **Cross-run same-workflow-same-inputs:** every hash input matches → same key → upstream
  dedups. Workflow author who wants distinct runs to hit upstream distinctly passes different
  `inputs` on `Engine::start()`; those change the compiled body → different fingerprint →
  different key.
- **Loop-back / goto with different context:** different context values → different compiled
  body → different fingerprint → new key → upstream treats as new op. Correct.
- **Loop-back with identical context:** same key → upstream dedups. Correct — this is the whole
  safety property of the feature.

### Header collision

If a workflow step manually sets the configured header via `Step::$parameters` (`in: header`),
`RequestInterface::withHeader()` **overwrites** — injector wins. Ledger event still fires; the
workflow's own value is lost. Acceptable: opt-in feature explicitly asks the engine to own the
key. Documented, not enforced.

### Streaming / non-JSON bodies

`compileRequest()` today always produces a rewindable string body (JSON payload or empty).
Canonicalization falls back to raw bytes for non-JSON content — key remains deterministic
because the same non-JSON payload produces the same bytes. Body stream is rewound after read
so the subsequent `httpClient->sendRequest($request)` sees an unread body — matches the
existing pattern at `StepExecutor.php:39-43`.

## Error Handling

- **Injector never throws.** Non-mutating verb, disabled feature, or null `definitionId` /
  `workflowId` on the context: all return an `InjectionResult` with `key === null`, silent
  no-op. Rationale: idempotency injection is a best-effort safety layer, never a reason to
  fail a step.
- **Malformed JSON body:** canonicalization falls back to raw bytes; key still computed and
  still deterministic. No warning; the body was already going to be sent as-is regardless.
- **Missing context identity** (test-only path — production `Engine::start()` guarantees both
  are set): null segments are treated as empty strings. Key is still computed but non-portable
  across runs.
- **Ledger append failure** (async path): `DatabaseEventLedger::append()` already catches
  `Throwable` and logs a warning without propagating. Injection itself always proceeds.
- **Header already set on request:** `RequestInterface::withHeader()` overwrites. Documented
  under Data Flow → Header collision.
- **Malformed extension values:** `optionalBool` / `optionalString` raise typed
  `ParserException` at parse time (existing helper behavior). Bad values never reach runtime.

## Testing

- **`tests/Execution/IdempotencyKeyInjectorTest.php`** (new):
  - Injects on `POST` / `PATCH` / `DELETE`; skips `GET` / `HEAD` / `OPTIONS` / `PUT`.
  - Disabled default + no per-step override → no injection.
  - Enabled default + `x-idempotency-key: false` step → no injection.
  - Disabled default + `x-idempotency-key: true` step → injection.
  - Key determinism: two calls with same request+step+context → identical key.
  - Key changes when body changes, when URI changes, when stepId changes, when definitionId
    changes, when workflowId changes.
  - Custom `x-idempotency-header` overrides the default header name.
  - JSON body key-order invariance: `{"a":1,"b":2}` and `{"b":2,"a":1}` bodies → same key.
  - Nested associative keys ordered recursively; positional list arrays kept as-is.
  - Non-JSON body path: raw bytes produce a deterministic key.
  - Overwrite behavior when the request already has the configured header set manually.

- **`tests/Execution/StepExecutorTest.php`** (extend):
  - Injection default off → no `Idempotency-Key` header in
    `$context->stepRequests['stepId']['headers']`.
  - Injection on + POST step → header present.
  - Injection on + GET step → no header.

- **`tests/Execution/HttpStepExecutorTest.php`** (extend):
  - Injection on + POST → `EventLedgerInterface::append()` called exactly once with
    `step.idempotency_key_injected` and payload matching the injected key/header.
  - Injection off → ledger never called with this event type.
  - Injection on + GET → ledger never called (no injection).

- **`tests/Parser/ParserTest.php`** (extend):
  - `x-idempotency-key: true` / `false` / absent → `Step::$idempotencyKey` matches
    `true`/`false`/`null`.
  - Non-bool value → `ParserException`.
  - `x-idempotency-header: 'X-Foo'` / absent → `Step::$idempotencyHeader` matches
    `'X-Foo'`/`null`.
  - Non-string value → `ParserException`.

- **`tests/LaravelArazzoServiceProviderBindingsTest.php`** (extend):
  - `IdempotencyKeyInjector` binds as singleton.
  - `arazzo.idempotency.enabled` / `arazzo.idempotency.header` config values propagate into
    the constructed injector: set both config keys, resolve the injector from the container,
    call `inject()` against a POST request with a step that has no `x-idempotency-key`
    override, and assert the resulting request carries the custom header name and a non-empty
    key.

## Key decisions (for future reference)

- **Client-side header only, no engine dedup ledger** — matches the roadmap's literal wording
  ("auto-generates and injects `Idempotency-Key` headers"). Engine-side dedup would require
  its own migration, its own outcome semantics for "ledger says done but upstream never
  actually processed," and its own replay UX story — a separate feature, not this one.
- **Cross-run stable key formula** — `definitionId + workflowId + stepId + requestFingerprint`
  dedups identical replays of the same workflow across separate `Engine::start()` calls, the
  strongest safety property available without a ledger. Different `inputs` still yield different
  request bodies → different keys, naturally.
- **`POST`/`PATCH`/`DELETE` only** — matches upstream API convention (Stripe, Square, PayPal,
  Adyen). `GET`/`HEAD`/`OPTIONS` are spec-idempotent; `PUT` is spec-idempotent but some APIs
  still key on it — deferred until a real use case surfaces.
- **Opt-in with per-step override** — mirrors 04's `strict_schema_validation` pattern. Global
  default + per-step opt-out/-in gives workflow authors coverage they can carve exceptions from
  without needing a document-level switch.
- **Post-compile injection in executors, not inside `ArazzoExpressionResolver::compileRequest()`**
  — keeps the resolver focused on expression evaluation; injector is trivially testable in
  isolation against any PSR-7 request; future filters (per-URL-host allowlist, per-tenant
  overrides) don't touch the resolver.
- **Configurable header name** — Adyen historically used `X-Idempotency-Key`; Square uses
  `Idempotency-Key`; vendor-specific APIs vary. One config knob is cheap; forcing the RFC-draft
  name on every workflow would break real integrations.
- **Ledger event async-path-only** — sync `StepExecutor` has no `EventLedgerInterface`
  dependency today; introducing one purely for a passive audit event would balloon its
  constructor and its ~30 test-site call sites. Async path already has the ledger threaded
  through `StepExecutionWorker` and hits it heavily; adding one more event type is cheap there.
- **`InjectionResult` value object, not a mutating method** — `HttpStepExecutor` needs `key` and
  `header` separately for the ledger event; `StepExecutor` only needs the mutated request.
  A single return type carries both consumers without duplicated hashing or extra
  `getLastKey()` state on the (otherwise stateless) injector.
- **Nullable injector/ledger constructor params on executors** — avoids a mechanical sweep of
  every existing `new StepExecutor(...)` / `new HttpStepExecutor(...)` test call site; production
  service provider always injects concrete instances. Same pragmatic pattern already used by
  other optional-behavior params (`$strictValidationDefault`).
