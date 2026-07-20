# Changelog

All notable changes to `alama/laravel-arazzo` will be documented in this file.

## Unreleased

### Added

- **Parser & Validator** — Loader → Parser → Validator pipeline for Arazzo 1.0.0 YAML/JSON documents: typed readonly DTOs, an expression lexer/AST/symbol table, and 39 validation rule classes across document/workflow/step/expression/action scopes.
- **Source Resolution** — `SourceResolver` with local/HTTP/cached fetchers and OpenAPI/Arazzo parsers, producing a `ResolvedSource` that extracts data via JSON Pointer.
- **Workflow Execution Logic** — `ExpressionEvaluator` (full AST visitor over input/step/request/response/output/component references) and `StepExecutor` (operation resolution, parameter/body resolution, HTTP dispatch, output extraction, success-criteria evaluation). Proven end-to-end by a real workflow execution test.
- **AI Generator** — `ArazzoGenerator` converts a natural-language trace into Arazzo YAML via a PSR-18 `AiClientInterface` (`OpenAiClient` implementation), injecting OpenAPI context and spec rules into the prompt.
- **React Flow UI** — Drag-and-drop OpenAPI endpoint canvas (`reactflow` + `@monaco-editor/react`) backed by `/api/arazzo/endpoints` and `/api/arazzo/generate`, plus refinements for per-node parameter/request-body/success-criteria/output configuration and dynamic spec loading.
- **Laravel Integration** — Service provider wiring for PSR-18/17 HTTP client bindings (Guzzle), publishable config, and container bindings for the generator and executor.
- **Playwright Browser Testing** — End-to-end browser test suite against the live UI (sidebar rendering, canvas drag, YAML generation).
- **Queue Integration (partial)** — `StepExecutionWorker` now closes the choreography loop: after a step succeeds, it resolves the owning `Workflow` via `DefinitionRegistryInterface` and calls `Engine::evaluate()` to dispatch newly-unlocked downstream steps. Added `SyncQueueDriver`, an in-memory recording implementation of `QueueDriverInterface` for tests.

### Added — not yet wired into the runtime

The following async execution subsystem is fully built and unit-tested in isolation, but **not bound in `LaravelArazzoServiceProvider` and not reachable from the app's actual (synchronous) execution path** (`WorkflowExecutor`/`StepExecutor`). Treat as scaffolding for a future event-driven engine, not a shipped feature:

- **Core Execution Engine** — framework-agnostic `Engine`, `DependencyAnalyzer`, immutable `WorkflowContext`, and `QueueDriverInterface`/`LockManagerInterface`/`HttpClientInterface` contracts with Laravel adapters (`LaravelQueueDriver`, `LaravelRedisLockManager`).
- **Dual-Store Persistence** — `RedisHotStateStore` (hot execution state), `DatabaseEventLedger` (append-only audit log; no migration shipped yet), `InMemoryDefinitionRegistry` (workflow versioning; process-local only, not viable across queue workers).
- **Step Execution Worker** — `StepExecutionWorker` (locking, idempotency, HTTP dispatch, engine re-entry) and the `StepExecuted` event. `ExecuteStepJob` is a plain object, not a real `ShouldQueue` Laravel job.
- **Zero-Code Data Pipelining** — `TypeCaster` and `JsonPathEvaluator` utilities, and `ArazzoExpressionResolver` (the async path's request/output compiler — still a stub: hardcoded `GET`, no OpenAPI operation resolution, no query/body params, no success-criteria evaluation). Not used by the live `StepExecutor`, which uses `ExpressionEvaluator`/`JsonPointer` instead.

Known gaps in this async chain, to resolve before it's wired up: double-dispatch prevention only holds for linear step chains, not diamond/fan-in DAGs (the idempotency check and `Engine::evaluate` both run against the job's own snapshot context rather than reloaded/merged persisted state); `InMemoryDefinitionRegistry` needs a shared/persistent backing store before choreography can work across real queue worker processes.

### In progress

- **Workflow Executor** — `DependencyGraph` (topological sort, cycle detection) described in the plan was never built; `WorkflowExecutor::execute()` still iterates `$workflow->steps` in array order with no dependency ordering.

### Known design debt

- `docs/superpowers/specs/2026-07-20-edge-mapping-ui-design.md` describes an edge-click configuration modal for data mapping between nodes. Never implemented; the UI refinement work instead solved node-level mapping via free-text textareas. Spec kept as-is pending a decision on whether to build it.
