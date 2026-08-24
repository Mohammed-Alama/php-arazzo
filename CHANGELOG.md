# Changelog

All notable changes to `alama/laravel-arazzo` will be documented in this file.

Entries under `## Unreleased` → `### Shipped` are promoted via `scripts/ship-plan.sh <slug>` — a deterministic promotion that moves the plan/spec under `docs/superpowers/plans/shipped/` + `docs/superpowers/specs/shipped/`, removes the roadmap stub, and appends this section.

## [Unreleased]

### Added
- **Execution preflight** (`PreflightValidator` + `PreflightFailureException`): resolves source descriptions, operation references, OpenAPI versions, reusable action components, and selector XPath versions with zero side effects before a run starts. Wired into both the synchronous executor and queue worker (fresh runs only); non-locally-registered sources downgrade to warnings so remote-fetched sources keep working. Laravel SP binds it automatically.
- **Shared step budget across nested invocations and queue jobs**: `ExecutionState`/`WorkflowContext` carry `stepsSpent` + workflow call stack; sub-workflow children inherit the parent pool (never reset), worker state payloads persist both fields across job boundaries; `ExecutionResult`/`SubWorkflowResult` expose final consumption.
- **Failure categories**: `StepFailed`/`RunFailed` events gain `category` (`criteria`, `schema`, `transport`, `authoring`, `execution`).
- **Raw response retention**: `StepExecutionOutcome` carries `rawBody` + `contentType`; step records persist them.
- **Severity model**: validator `Error`/`Warning` gain a `Severity` enum exposed via `toArray()`.
- Deterministic conformance harness: 9 golden fixtures executed through BOTH sync and queued adapters with normalized parity assertions; property tests for pointer round-trips, expression spelling equivalence, DAG acyclicity, retry ceilings, and state serialization.

### Changed
- **BREAKING**: single control-flow path - legacy `Engine` dispatcher deleted; `WorkflowEngine` is the only decision point and `StepOutcomeHandler` is now a thin adapter over it. `StepExecutionWorker` requires `WorkflowEngine` + `QueueDriverInterface`. Custom subclasses of these classes must be updated.
- **BREAKING**: `WorkflowExecutor` requires a `WorkflowEngine` as its second constructor argument.
- Transport-level HTTP failures are now converted into retryable synthetic-500 step outcomes (category `transport`) so `onFailure` retry actions apply uniformly in both adapters.
- Worker step attempts are stamped onto persisted state (`attempts`) so retry ceilings survive job boundaries.
- Expression engine: `${...}` runtime-expression spelling is now accepted everywhere (previously a syntax error); condition parsing normalizes both spellings to canonical `{$...}` form; bare `$expr` parameter/payload values resolve instead of being emitted literally.

### Fixed
- Duplicated `transition()` invocation in the synchronous loop (double retry accounting).
- Queue runs silently stalling on unknown goto/invoke target workflows (now ledger `execution.workflow_missing` + `RunFailed`).
- Missing terminal `RunCompleted`/`RunFailed` events on the queued adapter.


### Changed
- **BREAKING (Laravel bridge only)**: extracted framework-agnostic engine into new package `alama/arazzo-core`. `alama/laravel-arazzo` is now a thin bridge depending on the core. Existing consumers upgrade via `composer require alama/laravel-arazzo:^2.0@alpha` and (optionally) update FQCNs to `Alama\Arazzo\*`. Old `Alama\LaravelArazzo\*` FQCNs continue to resolve via `class_alias` throughout the 2.x line — planned removal in 3.0.
- Repository restructured as a Symplify monorepo hosting `packages/core` and `packages/laravel`. Tag-based subtree splits publish each package independently.

### Added
- `alama/arazzo-core` initial release (`1.0.0-alpha.1`): parser, validator, execution engine with in-memory reference drivers, expression resolver, schema validator, generator skeleton, OAK-ready contracts, `LicenseVerifierInterface` for future pro-tier gating.
- `LicenseVerifierInterface` + `NullLicenseVerifier` — foundation for future pro-tier entitlement enforcement (currently a no-op in OSS).

### Migration
- See `packages/laravel/UPGRADING.md` for consumer migration guide.
- See `packages/core/UPGRADING.md` for standalone core usage.

### Added

- Framework-agnostic PSR-14 event bus with 9 canonical lifecycle events (`RunStarted`, `RunCompleted`, `RunFailed`, `StepStarted`, `StepExecuted`, `StepRetried`, `StepFailed`, `CorrelationPending`, `CorrelationResumed`) under `Alama\LaravelArazzo\Events\`.
- `SimpleEventDispatcher` (in-memory) and `NullEventDispatcher` (no-op) — both PSR-14.
- `LedgerAppendingListener` — bridges the bus to existing `EventLedgerInterface` (auto-registered by the Laravel service provider when both `EventLedgerInterface` and `SimpleEventDispatcher` are container-bound).
- `IlluminatePsrEventDispatcher` — opt-in adapter, wires PSR-14 to `Illuminate\Events\Dispatcher`. Consumers bind manually:
  ```php
  $this->app->bind(
      \Psr\EventDispatcher\EventDispatcherInterface::class,
      \Alama\LaravelArazzo\Laravel\Events\IlluminatePsrEventDispatcher::class,
  );
  ```
- Requires `psr/event-dispatcher ^1.0`.
- `DependencyGraph` class representing topological ordering of workflow steps with cycle detection and unresolved reference checking.
- `StepDependsOnNoCycleRule` validator rule (error codes `step.dependson_no_cycle` and `step.dependson_unresolved_reference`).
- Full Arazzo 1.1.0 spec support: parser, validator, resolver, and executor now natively handle 1.1.0 documents alongside existing 1.0.0.
- `SpecVersion` enum, `Selector` DTO, `ExpressionType` enum, `SubWorkflowSuccessAction` / `SubWorkflowFailureAction`, `ArazzoDocument::$self`, `ActionKind::Invoke`, `ParameterIn::Querystring`.
- `XpathEvaluator` interface + `DomXpathEvaluator` (XPath 1.0 built-in via `ext-dom`). Bind a custom implementation to enable XPath 2.0 / 3.0 / 3.1.
- `SelectorEvaluator` routes Selector-shaped outputs / parameters by expression type (jsonpath / jsonpointer / xpath).
- `SubWorkflowInvoker` composes child workflows in-process; child `WorkflowContext` is isolated from parent `$steps.*` scope.
- 6 new validator rules: `step.dependson_validation` (cycle and unresolved refs), `selector.type_supported`, `subworkflow.invoke_target_resolves`, `parameter.querystring_operation_shape`, `document.self_uri_syntax`, `asyncapi.fields_require_11`.

### Changed

- `Engine`, `WorkflowExecutor`, `StepExecutor`, `StepExecutionWorker`, `StepOutcomeHandler`, `CorrelationResumer` constructors gain an optional `Psr\EventDispatcher\EventDispatcherInterface` param (defaults to `NullEventDispatcher`). Existing consumers unaffected; container users get `SimpleEventDispatcher` automatically.
- The 9 event names that previously reached the ledger via direct `EventLedger::append` now flow through the bus + `LedgerAppendingListener`. Ledger output byte-identical (verified by `LedgerRegressionTest`).
- Refactored `WorkflowExecutor` (sync mode) to execute steps in topological order rather than document array order, fixing an execution sequencing bug.
- **Breaking (Internal):** `Engine` and `StepOutcomeHandler` no longer accept `DependencyAnalyzer` in their constructors. They now instantiate it dynamically per-workflow.
- **Breaking (Internal):** `DependencyAnalyzer::__construct(DependencyGraph)` is now required, and `getRunnableSteps()` no longer takes an `$allSteps` parameter (it queries the bound graph instead).
- `arazzo` field is now strictly guarded: only `1.0.x` and `1.1.x` values are accepted. Documents with other versions are rejected at parse time.
- AsyncAPI-specific step fields (`action`, `channelPath`, `correlationId`) are now rejected on `1.0.0` documents. Move such workflows to `arazzo: 1.1.0`.

### Deprecated

- `Alama\LaravelArazzo\Execution\Events\StepExecuted` (unused by engine flow) in favor of `Alama\LaravelArazzo\Events\StepExecuted`. Removed in a future major.

### Shipped

- **PSR-14 Event Dispatcher Wiring** — Stub: [`docs/superpowers/roadmap/backend/phase-0-foundation/core-38-event-dispatcher-wiring.md`](../roadmap/backend/phase-0-foundation/core-38-event-dispatcher-wiring.md) Category: **core** · Phase: **0-foundation** · Tier: **OSS** Enables: pro-observability, `bridge-28` (Horizon/Telescope), `tenant-09` (context bridges), `health-23` (error triage).

- **DependencyGraph** — Stub: [`docs/superpowers/roadmap/backend/phase-0-foundation/core-37-dependency-graph.md`](../roadmap/backend/phase-0-foundation/core-37-dependency-graph.md) Category: **core** · Phase: **0-foundation** · Tier: **OSS** Closes known workflow-executor debt.

- **Arazzo 1.1.0 Spec Support** — Stub: [`docs/superpowers/roadmap/backend/phase-0-foundation/core-34-arazzo-1.1.0-spec.md`](../roadmap/backend/phase-0-foundation/core-34-arazzo-1.1.0-spec.md) Category: **core** · Phase: **0-foundation** · Tier: **OSS** Blocks: `ai-10-agent-routing`, `exec-07-saga-compensation`, `tenant-09-context-bridges`

- **Parser & Validator** — Loader → Parser → Validator pipeline for Arazzo 1.0.0 YAML/JSON documents: typed readonly DTOs, an expression lexer/AST/symbol table, and 39 validation rule classes across document/workflow/step/expression/action scopes.
- **Source Resolution** — `SourceResolver` with local/HTTP/cached fetchers and OpenAPI/Arazzo parsers, producing a `ResolvedSource` that extracts data via JSON Pointer.
- **Workflow Executor** — Framework-agnostic execution engine in `Alama\LaravelArazzo\Execution` on PSR-18/17/3 interfaces; consumes parsed `Workflow` DTOs, threads an immutable `WorkflowContext`, evaluates expressions through `ExpressionEvaluator`.
- **Workflow Execution Logic** — `ExpressionEvaluator` (full AST visitor over input/step/request/response/output/component references) and `StepExecutor` (operation resolution, parameter/body resolution, HTTP dispatch, output extraction, success-criteria evaluation).
- **Zero-Code Data Pipelining** — `ArazzoExpressionResolver` resolves OpenAPI operations, builds requests, extracts outputs (spec runtime expressions or JSONPath), and evaluates success criteria (simple/regex/jsonpath). `StepExecutor` orchestrates compile → send → record → extract → evaluate. `TypeCaster` + `JsonPathEvaluator` utilities.
- **CQRS & Event-Sourced Persistence** — Three IDs (`definitionId`/`workflowId`/`executionId`) replace the single overloaded `WorkflowContext::$definitionId`. `RedisHotStateStore` (hot state), `DatabaseEventLedger` (append-only audit log), `InMemoryDefinitionRegistry` (workflow versioning). Definition registry persists `ArazzoDocument::$rawRoot` verbatim and reconstructs via `Parser::parse()` on read.
- **Native Asynchronous Control Flow** — `StepOutcomeHandler` owns all retry/goto/end decision logic, called by `StepExecutionWorker` (HTTP path) and `CorrelationResumer` (AsyncAPI resume path). `StepProtocolExecutorInterface` (`HttpStepExecutor` / `AsyncApiStepExecutor`) removes protocol branching. `RunExecuteStepJob` (real `ShouldQueue`), `LaravelQueueDriver`, `LaravelRedisLockManager`, `Psr18HttpClient`, `WebhookResumeController`, `ResumeCorrelationJob`, `PendingCorrelationRegistryInterface` / `DatabasePendingCorrelationRegistry`.
- **Strict Runtime Schema Validation** — `arazzo.strict_schema_validation` config + type-safe `x-strict-validation` parsing; `SchemaValidationException`; full JSON Schema keyword coverage on inputs, outputs, parameters, request bodies.
- **Idempotency & Replay Safeguards** — `IdempotencyKeyInjector` wired into both `StepExecutor` (sync) and `HttpStepExecutor` (async). Per-step `idempotencyKey` / `idempotencyHeader` DTO fields (parsed from `x-idempotency-key` / `x-idempotency-header`). `arazzo.idempotency.enabled` / `.header` config. Deterministic key computation + method-filter + end-to-end verification.
- **AI Generator** — `ArazzoGenerator` converts a natural-language trace into Arazzo YAML via a PSR-18 `AiClientInterface` (`OpenAiClient` implementation), injecting OpenAPI context and spec rules into the prompt.
- **React Flow UI** — Drag-and-drop OpenAPI endpoint canvas (`reactflow` + `@monaco-editor/react`) backed by `/api/arazzo/endpoints` and `/api/arazzo/generate`; per-node parameter/request-body/success-criteria/output configuration and dynamic spec loading.
- **Laravel Integration** — Service provider wiring for PSR-18/17 HTTP client bindings (Guzzle), publishable config, and container bindings for the generator and executor.
- **Playwright Browser Testing** — End-to-end browser test suite against the live UI (sidebar rendering, canvas drag, YAML generation).

### Known design debt

- `docs/superpowers/specs/2026-07-20-edge-mapping-ui-design.md` describes an edge-click configuration modal for data mapping between nodes. Never implemented; UI refinement solved node-level mapping via free-text textareas. Spec kept as-is pending a decision on whether to build it.
