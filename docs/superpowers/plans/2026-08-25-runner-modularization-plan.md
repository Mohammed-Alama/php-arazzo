# Runner Modularization Implementation Plan

> **Status (2026-08-25, implementation session):**
>
> | Task | State | Notes |
> |------|-------|-------|
> | 1 · OTel bootstrap | ✅ Done | `OtelSetup` (otlp/console/**file**/memory/none), `TraceContextPropagator`; no exporter-file pkg exists — file export implemented via `StreamTransportFactory` |
> | 2 · RetryPolicy | ✅ Done | `Policy/RetryPolicy`, `BackoffCalculatorInterface`, `ExponentialBackoffCalculator`; engine ctor keeps `maxRetryAttempts:` BC |
> | 3 · LockStrategy | ✅ Done | `Contract/LockStrategyInterface`, File/Null/Pessimistic strategies; `LockManagerInterface extends LockStrategyInterface` |
> | 4 · Registry + SubWorkflowExecutor | ✅ Done | Chain-of-responsibility registry (`register(name, executor)`); executor shares parent budget/call-stack |
> | 5 · ExecutionContext | ✅ Done | `State/*` VO set; **engine stays canonical on ExecutionState**, facade converts at the boundary |
> | 6 · StateStore/File | ✅ Done | Reused existing `Context/Contracts/StateStoreInterface`; added `FileStateStore` (+delete/sanitize), `InMemoryStateStore` |
> | 7 · Async handler split | ⏸ Deferred | Worker stayed cohesive (~380 lines); decomposition postponed until churn justifies it |
> | 8 · CLI runner | ✅ Done | `Cli/CliRunner` drains `SyncQueueDriver` through the real worker (parity by construction) + `CliRunResult`, Null ledger, InProcess registry |
> | 9 · Parity tests | ✅ Done | `AdapterParityTest` (sync vs queue-path, shared step stack) |
> | 10 · Laravel bindings | ✅ Done | Deps synced; `LockStrategyInterface` alias bound; fixed `LaravelRedisLockManager::tryAcquire/release` ownership |
> | 11 · Gates & metrics | ✅ Done | pint/phpstan/pest green both packages; docs regenerated |
>
> Verification at close: core **742+ passed / 0 failed**, laravel **56 passed**, phpstan clean ×2, pint clean.


**Goal:** Decompose Runner into cohesive components with clear boundaries, reduce churn, improve testability and observability via OpenTelemetry. Support both sync/async equally, high-frequency sub-workflows, CLI file-based persistence.

**Architecture:** Extract pure policy objects, introduce handler composition for async adapter, unify state model, integrate OpenTelemetry for traces/metrics/logs. Sync/async/CLI adapters share `WorkflowEngine` and `ProtocolExecutorRegistry`.

**Tech Stack:** PHP 8.4, PSR-7/PSR-18, OpenTelemetry PHP SDK, Pest PHP, existing contracts.

---

## File Map

### New Files
- `packages/core/src/Runner/Policy/RetryPolicy.php`
- `packages/core/src/Runner/Policy/BackoffCalculator.php`
- `packages/core/src/Runner/Policy/BackoffCalculatorInterface.php`
- `packages/core/src/Runner/Contract/LockStrategyInterface.php`
- `packages/core/src/Runner/Contract/StateStoreInterface.php`
- `packages/core/src/Runner/Protocol/ProtocolExecutorRegistry.php`
- `packages/core/src/Runner/Protocol/ProtocolExecutorRegistryInterface.php`
- `packages/core/src/Runner/Protocol/SubWorkflowExecutor.php`
- `packages/core/src/Runner/State/ExecutionContext.php`
- `packages/core/src/Runner/State/StepResult.php`
- `packages/core/src/Runner/State/Budget.php`
- `packages/core/src/Runner/State/ErrorEntry.php`
- `packages/core/src/Runner/State/FileStateStore.php` (CLI)
- `packages/core/src/Runner/State/InMemoryStateStore.php` (testing)
- `packages/core/src/Runner/Async/StateReconciler.php`
- `packages/core/src/Runner/Async/PreflightGuard.php`
- `packages/core/src/Runner/Async/StepExecutor.php`
- `packages/core/src/Runner/Async/CriteriaEvaluator.php`
- `packages/core/src/Runner/Async/TransitionDispatcher.php`
- `packages/core/src/Runner/Async/EventEmitter.php`
- `packages/core/src/Runner/Cli/CliRunner.php` (CLI entry point)
- `packages/core/src/Runner/Cli/CliStateStore.php` (alias to FileStateStore)
- `packages/core/src/Runner/Telemetry/OtelSetup.php` (OTel bootstrap)
- `packages/core/src/Runner/Telemetry/TraceContextPropagator.php` (for async trace linking)

### Modified Files
- `packages/core/src/Runner/Execution/WorkflowEngine.php` — inject `RetryPolicy`, remove inline retry logic, accept/return `ExecutionContext`
- `packages/core/src/Runner/Execution/StepExecutionWorker.php` — delegate to handlers, inject `LockStrategy`, `StateStore`, OTel tracer
- `packages/core/src/Runner/Execution/Contracts/LockManagerInterface.php` — extend `LockStrategyInterface`
- `packages/core/src/Runner/Execution/RunControlFlow.php` — add `ProtocolExecutorRegistry`, `StateStore`, OTel tracer
- `packages/core/src/Runner/Execution/WorkflowExecutor.php` — use `ProtocolExecutorRegistry`, `ExecutionContext`, `StateStore`
- `packages/core/src/Runner/Context/ExecutionState.php` — migrate to `ExecutionContext` (or deprecate)
- `packages/core/src/Runner/Context/WorkflowContext.php` — migrate to `ExecutionContext` (or deprecate)
- `packages/core/src/Runner/Execution/StepProtocolExecutorInterface.php` — ensure `SubWorkflowExecutor` implements
- `packages/core/composer.json` — add `open-telemetry/sdk`, `open-telemetry/exporter-otlp`, `open-telemetry/exporter-file`
- Laravel bindings: bind new interfaces

### Test Files
- `packages/core/tests/Runner/Policy/RetryPolicyTest.php`
- `packages/core/tests/Runner/Policy/BackoffCalculatorTest.php`
- `packages/core/tests/Runner/Protocol/ProtocolExecutorRegistryTest.php`
- `packages/core/tests/Runner/Protocol/SubWorkflowExecutorTest.php`
- `packages/core/tests/Runner/State/ExecutionContextTest.php`
- `packages/core/tests/Runner/State/FileStateStoreTest.php`
- `packages/core/tests/Runner/Async/StateReconcilerTest.php`
- `packages/core/tests/Runner/Async/PreflightGuardTest.php`
- `packages/core/tests/Runner/Async/StepExecutorTest.php`
- `packages/core/tests/Runner/Async/CriteriaEvaluatorTest.php`
- `packages/core/tests/Runner/Async/TransitionDispatcherTest.php`
- `packages/core/tests/Runner/Async/EventEmitterTest.php`
- `packages/core/tests/Runner/Cli/CliRunnerTest.php`
- `packages/core/tests/Runner/Telemetry/OtelIntegrationTest.php`
- Integration: `packages/core/tests/Runner/AdapterParityTest.php` (sync vs async same result)
- Integration: `packages/core/tests/Runner/SubWorkflowParityTest.php` (nested workflows sync/async)

---

## Task 1: Add OpenTelemetry Dependencies & Bootstrap

- [ ] Add to `packages/core/composer.json`: `open-telemetry/sdk`, `open-telemetry/exporter-otlp`, `open-telemetry/exporter-file`, `open-telemetry/api`
- [ ] Create `OtelSetup` class: configure tracer provider, meter provider, logger provider
- [ ] Support configuration via env: `OTEL_EXPORTER_OTLP_ENDPOINT`, `OTEL_PHP_AUTOLOAD_ENABLED`, `ARAZZO_OTEL_EXPORTER=file|otlp|none`
- [ ] Create `TraceContextPropagator` for async trace context propagation (W3C traceparent)
- [ ] Add test: `OtelIntegrationTest` — verify spans emitted, attributes present
- [ ] Commit `feat: OpenTelemetry bootstrap and configuration`

## Task 2: Extract RetryPolicy & BackoffCalculator

- [ ] Create `BackoffCalculatorInterface` with `calculate(int $baseDelay, int $attempt, float $multiplier): int`
- [ ] Create `ExponentialBackoffCalculator` implementing interface
- [ ] Create `RetryPolicy` class encapsulating maxAttempts, backoffMultiplier, calculator
- [ ] Move `retryDelaySeconds` logic from `WorkflowEngine` to `RetryPolicy::calculateDelay()`
- [ ] Add OTel attributes to retry spans: `retry.attempt`, `retry.delay`, `retry.exhausted`
- [ ] Update `WorkflowEngine` to accept `RetryPolicy` in constructor
- [ ] Add tests: `RetryPolicyTest`, `ExponentialBackoffCalculatorTest`
- [ ] Run `cd packages/core && vendor/bin/pest tests/Runner/WorkflowEngineTest.php`; expect PASS
- [ ] Commit `feat: extract RetryPolicy and BackoffCalculator from WorkflowEngine`

## Task 3: Introduce LockStrategyInterface

- [ ] Create `LockStrategyInterface` (acquire, tryAcquire, release)
- [ ] Create `PessimisticLockStrategy` wrapping current `LockManagerInterface::acquire`
- [ ] Create `FileLockStrategy` for CLI (flock on .lock files)
- [ ] Create `NullLockStrategy` for testing
- [ ] Update `LockManagerInterface` to extend `LockStrategyInterface`
- [ ] Update `StepExecutionWorker` to use `LockStrategyInterface` instead of direct lock manager call
- [ ] Add tests: `PessimisticLockStrategyTest`, `FileLockStrategyTest`, `NullLockStrategyTest`
- [ ] Run `cd packages/core && vendor/bin/pest tests/Runner/StepExecutionWorkerTest.php`; expect PASS
- [ ] Commit `feat: introduce LockStrategyInterface for pluggable locking`

## Task 4: ProtocolExecutorRegistry & SubWorkflowExecutor

- [ ] Create `ProtocolExecutorRegistryInterface` and `ProtocolExecutorRegistry` implementation
- [ ] Register existing executors: `HttpStepExecutor`, `AsyncApiStepExecutor`, `DefaultOpenApiExecutor`
- [ ] Create `SubWorkflowExecutor` implementing `StepProtocolExecutorInterface` for `invoke` transitions
- [ ] `SubWorkflowExecutor` creates child `ExecutionContext`, runs `WorkflowEngine` loop, returns outcome with sub-workflow outputs
- [ ] Add OTel span for `subworkflow.invoke` with `subworkflow.depth` attribute
- [ ] Update `StepExecutionWorker::findExecutor()` to use registry
- [ ] Update `WorkflowExecutor` to use registry
- [ ] Add tests: `ProtocolExecutorRegistryTest`, `SubWorkflowExecutorTest` (nested workflows, outputs, errors)
- [ ] Run `cd packages/core && vendor/bin/pest tests/Runner/Execution/`; expect PASS
- [ ] Commit `feat: ProtocolExecutorRegistry and SubWorkflowExecutor`

## Task 5: Unified ExecutionContext

- [ ] Create `ExecutionContext` class combining `ExecutionState` + `WorkflowContext` data
- [ ] Implement read-only getters and builder-style `with*` methods returning new instance
- [ ] Add `Budget` value object (maxSteps, stepsSpent, maxWorkflowDepth, workflowCallStack)
- [ ] Add `StepResult` value object (statusCode, request, response, outputs, inputs, attempts, failureCategory)
- [ ] Add `ErrorEntry` value object (type, stepId, attempts, message, timestamp)
- [ ] Update `WorkflowEngine::transition()` to accept/return `ExecutionContext`
- [ ] Update `WorkflowEngine::evaluateWorkflowOutputs()` to accept `ExecutionContext`
- [ ] Update `StepExecutionWorker` to use `ExecutionContext` throughout
- [ ] Update `WorkflowExecutor` to use `ExecutionContext`
- [ ] Add tests: `ExecutionContextTest`, `BudgetTest`, `StepResultTest`
- [ ] Run full runner test suite; expect PASS
- [ ] Commit `feat: unify ExecutionState and WorkflowContext into ExecutionContext`

## Task 6: StateStoreInterface & FileStateStore (CLI)

- [ ] Create `StateStoreInterface` (save, load, delete)
- [ ] Create `FileStateStore` — stores `ExecutionContext` as JSON in `./storage/executions/{executionId}.json`
- [ ] Create `InMemoryStateStore` for testing
- [ ] Update `RunPersistence` to use `StateStoreInterface`
- [ ] Add test: `FileStateStoreTest` — persist, load, delete, TTL ignored (files don't expire)
- [ ] Commit `feat: StateStoreInterface with FileStateStore for CLI`

## Task 7: Decompose StepExecutionWorker into Handlers

- [ ] Create `StateReconciler` — `reconcile(WorkflowContext $jobContext, string $executionId): ExecutionContext`
- [ ] Create `PreflightGuard` — `ensurePreflight(ExecutionContext $context, DefinitionRegistryInterface $registry): void`
- [ ] Create `StepExecutor` — `execute(Step $step, ExecutionContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome`
- [ ] Create `CriteriaEvaluator` — `evaluate(Step $step, ExecutionContext $context, ArazzoDocument $document): bool`
- [ ] Create `TransitionDispatcher` — `dispatch(Transition $transition, ExecutionContext $context, ...): void` (persist, emit, enqueue)
- [ ] Create `EventEmitter` — wraps `EventDispatcherInterface`, emits domain events
- [ ] Each handler creates OTel span for its operation
- [ ] Refactor `StepExecutionWorker::handle()` to compose handlers
- [ ] Inject OTel tracer into `RunControlFlow`, pass to handlers
- [ ] Add tests for each handler (mock dependencies, test single responsibility)
- [ ] Run `cd packages/core && vendor/bin/pest tests/Runner/StepExecutionWorkerTest.php`; expect PASS
- [ ] Commit `refactor: decompose StepExecutionWorker into focused handlers`

## Task 8: CLI Runner Adapter

- [ ] Create `CliRunner` — entry point for `arazzo run` command
- [ ] Uses `WorkflowExecutor` (sync) with `FileStateStore`
- [ ] Accepts workflow file (YAML/JSON), inputs (JSON file or stdin), outputs (JSON file or stdout)
- [ ] Configures OTel for file export (`ARAZZO_OTEL_EXPORTER=file`) to `./storage/traces/`
- [ ] Handles sub-workflows via `SubWorkflowExecutor` (same process)
- [ ] Add test: `CliRunnerTest` — end-to-end workflow execution via CLI
- [ ] Commit `feat: CLI runner adapter with file persistence`

## Task 9: Adapter Parity & Integration Tests

- [ ] Create `AdapterParityTest` — run same document through `WorkflowExecutor` (sync), `StepExecutionWorker` (async via sync queue driver), `CliRunner`, assert identical `ExecutionResult`
- [ ] Test cases: linear workflow, retry, goto, sub-workflow invoke (nested 3 levels), suspension/resume, failure actions, workflow outputs, parallel steps via dependsOn
- [ ] Add fixture documents under `packages/core/tests/fixtures/runner/parity/`
- [ ] Run `cd packages/core && vendor/bin/pest tests/Runner/AdapterParityTest.php`; expect PASS
- [ ] Commit `test: sync/async/CLI adapter parity verification`

## Task 10: Laravel Bindings Update

- [ ] Update `packages/laravel/src/ServiceProvider.php` to bind new interfaces
- [ ] Bind `LockStrategyInterface` → `PessimisticLockStrategy` (using Laravel cache lock)
- [ ] Bind `StateStoreInterface` → `DatabaseStateStore` (Eloquent model) / `RedisStateStore`
- [ ] Bind `ProtocolExecutorRegistry` with all executors including `SubWorkflowExecutor`
- [ ] Bind `RetryPolicy` from config
- [ ] Configure OTel via Laravel package (auto-discovery)
- [ ] Run `cd packages/laravel && vendor/bin/pest`; expect PASS
- [ ] Commit `chore: update Laravel bindings for modularized Runner`

## Task 11: Cleanup & Metrics Verification

- [ ] Remove deprecated `ExecutionState`/`WorkflowContext` if fully migrated
- [ ] Run `make quality-gates` — expect PASS
- [ ] Run churn analysis: `php scripts/generate-docs.php` → verify `docs/generated/churn-hotspots.md` shows Runner < 20%
- [ ] Run coupling analysis: verify Runner fan-out ≤ 3 in `docs/generated/coupling-metrics.md`
- [ ] Run OTel verification: execute sample workflow, verify spans in file/OTLP export
- [ ] Commit `chore: post-modularization cleanup and metrics verification`

---

## Dependencies

```
Task 1 (OTel Bootstrap)       ───┐
Task 2 (RetryPolicy)          ───┤
Task 3 (LockStrategy)         ───┤
Task 4 (ProtocolRegistry)     ───┤
Task 5 (ExecutionContext)     ───┼──► Task 7 (Handlers) ──► Task 8 (CLI) ──► Task 9 (Parity) ──► Task 10 (Laravel) ──► Task 11 (Cleanup)
Task 6 (StateStore/File) ─────┘
```

Tasks 1-6 can run in parallel. Task 7 depends on 1-6. Tasks 8-11 are sequential.

---

## Rollback Plan

- Each task commits independently with passing tests
- Feature flag: `RunControlFlow::$useModularHandlers` (default false during transition)
- If parity fails, flip flag, investigate, re-enable

---

## Estimated Effort

| Task | Estimate |
|------|----------|
| 1: OTel Bootstrap | 0.5 days |
| 2: RetryPolicy | 0.5 days |
| 3: LockStrategy | 0.5 days |
| 4: ProtocolRegistry + SubWorkflowExecutor | 1.5 days |
| 5: ExecutionContext | 1.5 days |
| 6: StateStore/FileStateStore | 0.5 days |
| 7: Handler Decomposition | 2 days |
| 8: CLI Runner | 1 day |
| 9: Parity Tests | 1 day |
| 10: Laravel Bindings | 0.5 days |
| 11: Cleanup/Metrics | 0.5 days |
| **Total** | **~10 days** |
