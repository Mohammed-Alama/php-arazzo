# Zero-Code Data Pipelining — Design

Roadmap seed: [docs/superpowers/roadmap/01-zero-code-data-pipelining.md](../roadmap/01-zero-code-data-pipelining.md).

> **Addendum (2026-07-20) — Arazzo 1.1.0 confirmed real:** two "Out of scope" calls below were
> made before 1.1.0's release was confirmed and are now stale. `CriterionType::XPath` was
> deferred because there was "no XML use case in this codebase" — 1.1.0's Selector Object
> makes `xpath` a real, spec-required type (pinned to `xpath-10`/`20`/`30`/`31`). And
> `WorkflowRef`/`SourceRef` AST nodes were deferred as "likely relevant to a future cross-module
> item" — that future item has arrived: AsyncAPI `channelPath` values are cross-source
> references (`{$sourceDescriptions.<name>}#/channels/...`). Neither is in scope for *this*
> plan (still correctly scoped to 1.0.0 parity) — but don't let a future implementer read the
> "Out of scope" bullets below as still-valid reasoning; they're historical context now, not a
> current decision. See `docs/superpowers/roadmap/ROADMAP.md`'s "Arazzo 1.1.0" section and
> `tests/fixtures/parser/arazzo-1.1-cross-protocol-saga.yaml` for the target shape.

## Starting point: this is not greenfield

Three pieces of scaffolding already exist (see `CHANGELOG.md`, "Added — not yet wired into the
runtime"): `TypeCaster`, `JsonPathEvaluator`, and `ArazzoExpressionResolver`. None are used by the
live, synchronous execution path (`WorkflowExecutor` → `StepExecutor`), which instead has its own,
more complete inline implementation built on `ExpressionEvaluator` / `JsonPointer` /
`VariableContext` / `OpenApiParser`. `ArazzoExpressionResolver` is consumed only by
`StepExecutionWorker`, the not-yet-wired async choreography path — itself out of scope here
(roadmap item 03).

This design **unifies the two** into one implementation of `ExpressionResolverInterface`, used by
both the sync and (future) async orchestrators, rather than finishing `ArazzoExpressionResolver` as
a second, independently-maintained copy of logic `StepExecutor` already has.

## Scope

**In scope:**
- Finish `ArazzoExpressionResolver` into the real, spec-aware request compiler / output extractor /
  success-criteria evaluator.
- Move the OpenAPI-resolution, parameter-building, body-replacement, and success-criteria logic
  currently inline in `StepExecutor::execute()` into the resolver; `StepExecutor` becomes a thin
  orchestrator.
- Unify on `WorkflowContext` (immutable) as the one context type; delete `VariableContext`.
- Add OpenAPI-schema-driven type casting for request parameters and extracted outputs, via
  `TypeCaster`.
- Keep JSONPath as a supported extraction mechanism — not just as a convenience extension, but
  because it's what the spec's `CriterionType::JsonPath` success-criterion type actually requires.
- Fix a pre-existing gap: `ExpressionEvaluator` doesn't implement `HttpMetaRef` even though the
  expression parser already emits it for `$statusCode`/`$method`/`$url`.

**Out of scope (explicitly deferred):**
- Wiring `StepExecutionWorker` into a real queue/dispatch pipeline, or fixing its known
  double-dispatch/registry gaps — roadmap item 03 ("Native Async Control Flow").
- `CriterionType::XPath` — still out of scope for *this* plan (1.0.0 parity only). Throws a
  dedicated "unsupported" exception rather than a fake implementation. Correction: the original
  reason ("no XML use case in this codebase") no longer holds — Arazzo 1.1.0's Selector Object
  makes `xpath` a real, spec-required type. Real scope, deferred to whichever item picks up the
  1.1.0 Selector Object (see roadmap item 01's "1.1.0 delta"), not dropped.
- `WorkflowRef` / `SourceRef` expression AST nodes (cross-workflow/cross-source references) — not
  needed for step-level compile/extract/evaluate in this (1.0.0-scoped) plan. Correction: the
  "future cross-module item" this was deferred to is now concrete — AsyncAPI `channelPath`
  cross-source references, roadmap item 03's 1.1.0 delta.
- Full OpenAPI-schema *validation* (as opposed to best-effort casting) — that's roadmap item 04
  ("Strict Runtime Schema Validation"), which depends on this item. Casting here is deliberately
  forgiving (see Error Handling) so it doesn't duplicate item 04's job.

## Architecture

### Context: `WorkflowContext` replaces `VariableContext`

`WorkflowContext` (immutable — `getDefinitionId`/`getInputs`/`getSteps`/`getComponents`, plus
`withStepResult`) already has the read shape `ExpressionEvaluator` needs. It gains immutable
mutators mirroring `VariableContext`'s current in-place setters:

- `withStepRequest(string $stepId, array $request): self`
- `withStepResponse(string $stepId, array $response): self`
- `withStepOutput(string $stepId, string $key, mixed $value): self`

Each returns a new instance; `steps[$stepId]` keeps the existing
`['request' => …, 'response' => …, 'outputs' => …]` shape, so no read-side changes are needed
elsewhere. `VariableContext` and `ConditionEvaluator` (see below) are deleted once callers move
over — no compatibility shim.

### `ExpressionEvaluator`

Retyped from `VariableContext` to `WorkflowContext`. Gains a case for `HttpMetaRef` — resolves
`$statusCode`/`$method`/`$url` against the *current* step the same way `StepRef`'s
`RequestPart`/`ResponsePart` cases already do. Today the parser emits this AST node but the
evaluator silently returns `null` for it; `ConditionEvaluator` works around the gap with a
string-rewrite hack (`$statusCode` → `$steps.<id>.response.statusCode`) before evaluation. That
hack goes away once `HttpMetaRef` is handled directly.

### `OpenApiParser`

`findOperation()` extended to also return the resolved `Operation` object (not just
`[method, path]`), so callers can read `parameters` and `responses` schemas for casting and
header/param resolution.

### `ArazzoExpressionResolver` (the real `ExpressionResolverInterface` implementation)

- **`compileRequest(Step $step, WorkflowContext $context): RequestInterface`** — resolves the
  operation via `OpenApiParser`; builds query/header/path parameters from `Step::parameters`
  (evaluating `Expression` values via `ExpressionEvaluator`); applies `requestBody` replacements
  via `JsonPointer`; casts parameter values against the matching OpenAPI parameter schema via
  `TypeCaster` (best-effort); returns a fully-formed PSR-7 request.
- **`extractOutputs(Step $step, WorkflowContext $context): array`** — signature change from
  `(Step, array $responseData)`: needs the whole context, not just the raw response body, because
  runtime expressions can reference `$request`, `$inputs`, or other steps, not only the current
  response. For each `Step::outputs` entry: if the expression is a real Arazzo runtime expression,
  evaluate via `ExpressionEvaluator`; if it's bare JSONPath (detected by an unambiguous `$.`
  prefix — no Arazzo expression root starts with a dot immediately after `$`), evaluate via
  `JsonPathEvaluator`. Casts the result against the OpenAPI response schema at that location when
  resolvable (best-effort).
- **`evaluateSuccessCriteria(Step $step, WorkflowContext $context): bool`** — new interface method,
  replaces `ConditionEvaluator`. Properly honors `SuccessCriterion`'s `context` / `condition` /
  `type` fields (previously ignored — `ConditionEvaluator` only handled ad-hoc `simple`-shaped
  strings): resolves `context` via `ExpressionEvaluator` (defaulting to `$response.body` when
  absent, per spec), then dispatches on `type`:
  - `Simple` (default) — `condition` is a runtime-expression comparison, evaluated as today.
  - `Regex` — `condition` matched as a pattern against the stringified context value.
  - `JsonPath` — `condition` run as a JSONPath query against the context value; truthy if it
    matches.
  - `XPath` — throws `UnsupportedCriterionTypeException` immediately.

### `TypeCaster`

Adds `asFloat`/`asBoolean` (OpenAPI `number`/`boolean` types have no home today). All cast methods
change from throwing on failure to returning the original, uncast value (see Error Handling).

## Data Flow

`StepExecutor::execute()` becomes an orchestrator:

```
$request  = $resolver->compileRequest($step, $context)
$context  = $context->withStepRequest($step->stepId, [...])
$response = $httpClient->sendRequest($request)          // existing try/catch → synthetic 500 preserved
$context  = $context->withStepResponse($step->stepId, [...])
$outputs  = $resolver->extractOutputs($step, $context)
foreach ($outputs as $key => $value) {
    $context = $context->withStepOutput($step->stepId, $key, $value)
}
$success  = $resolver->evaluateSuccessCriteria($step, $context)
return new StepResult($step->stepId, $success, $outputs)
```

`StepExecutionWorker` needs one mechanical fix to keep compiling against the changed interface: it
currently calls `extractOutputs($step, [])` before ever recording the response into context. That
becomes `$context = $context->withStepResponse(...)` followed by
`extractOutputs($step, $context)`, mirroring the sync flow above. This is the minimum needed for
the interface change to compile — it does not fix `StepExecutionWorker`'s other known gaps
(double-dispatch on diamond/fan-in DAGs, `InMemoryDefinitionRegistry` not being shared across
workers), which stay deferred to roadmap item 03. It remains undispatched — nothing wires it into
an actual queue — until that item lands.

**Wiring:** `LaravelArazzoServiceProvider` adds a binding,
`ExpressionResolverInterface::class → ArazzoExpressionResolver`, and `StepExecutor`'s constructor
swaps its direct `ExpressionEvaluator`/param-building dependencies for the resolver.

## Error Handling

- **Network/transport failure** (`httpClient->sendRequest` throws) — unchanged: caught, recorded as
  a synthetic `500` response with the exception message in the body, so success-criteria evaluation
  still runs (and correctly reports failure) instead of throwing out of `execute()`.
- **Unresolvable operation** (`operationId`/`operationPath` doesn't match the OpenAPI doc) —
  `OpenApiParser` already throws `RuntimeException`; left as a hard failure since there's no
  request to send.
- **Type-cast failure** (param or output doesn't match its declared OpenAPI type) — best-effort:
  falls back to the raw, uncast value rather than failing the step. Real-world APIs don't always
  perfectly match their declared schema, and failing loudly here would duplicate roadmap item 04's
  job. Not silent, though — logs a warning so schema drift stays visible without failing the step.
- **`CriterionType::XPath`** — throws `UnsupportedCriterionTypeException` immediately, rather than
  silently returning `false`, so a workflow author knows their spec uses something unsupported
  instead of seeing a mysterious failed step.
- **Malformed expression syntax** — already handled upstream by `Expression::astOrError()` /
  `ExpressionSyntaxException`; unchanged.

## Testing

- `ArazzoExpressionResolverTest.php`, `TypeCasterTest.php`, `JsonPathEvaluatorTest.php`,
  `WorkflowContextTest.php` — extended for the new real behavior (OpenAPI-driven param/body
  building, `HttpMetaRef`-based criteria, cast fallback-not-throw, new `with*` mutators).
- `ConditionEvaluatorTest.php` — retired; cases re-expressed as `evaluateSuccessCriteria` tests
  using real `SuccessCriterion` DTOs with `type: simple`, plus new `regex` and `jsonpath` cases.
- `ExpressionEvaluatorTest.php` — extended with `HttpMetaRef` cases; fixtures retyped from
  `VariableContext` to `WorkflowContext`.
- `StepExecutionWorkerTest.php` — should keep passing largely unchanged (already mocks
  `ExpressionResolverInterface`), confirming the interface contract didn't silently drift.
- New end-to-end test exercising `StepExecutor` against a small fixture OpenAPI doc, to prove
  sync-path parity after the refactor.

## Key decisions (for future reference)

- **Unify, don't duplicate**: sync and async paths share one resolver rather than each having a
  complete, independently-maintained implementation.
- **JSONPath stays**, both as an output-extraction convenience (bare `$.` prefix) and because it's
  required for `CriterionType::JsonPath` success criteria — not something to drop in favor of pure
  Arazzo runtime expressions.
- **Casting is schema-driven now** (pulled forward from item 04) but **best-effort**: failures fall
  back to the raw value with a logged warning, not a hard failure. Strict validation is item 04's
  job.
- **`WorkflowContext` wins** over `VariableContext` — matches the roadmap stub's own stated design
  intent, and removes the split-brain between the two execution paths' context types.
