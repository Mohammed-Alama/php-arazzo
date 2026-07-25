# Changelog

All notable changes to `alama/laravel-arazzo` will be documented in this file.

Entries under `## Unreleased` → `### Shipped` are promoted via `scripts/ship-plan.sh <slug>` — a deterministic promotion that moves the plan/spec under `docs/superpowers/plans/shipped/` + `docs/superpowers/specs/shipped/`, removes the roadmap stub, and appends this section.

## Unreleased

### Shipped

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
- `DependencyGraph` (topological sort, cycle detection) described in the workflow-executor plan was never built; `WorkflowExecutor::execute()` still iterates `$workflow->steps` in array order with no dependency ordering. Async path uses proper dispatch (`Engine`/`StepExecutionWorker`), so this is a gap only in the sync path.
