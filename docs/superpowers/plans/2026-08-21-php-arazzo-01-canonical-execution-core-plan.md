# Canonical Arazzo Execution Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace divergent synchronous and queue control-flow implementations with one deterministic Arazzo transition engine.

**Architecture:** `WorkflowEngine` evaluates one transition at a time and returns a serializable execution state/result. A synchronous runner loops over transitions; the queue worker applies one transition, persists state, and dispatches the next job. Existing `StepOutcomeHandler` becomes a decision component or is removed after its behavior is ported.

**Tech Stack:** PHP 8.4, PSR event dispatcher, PSR-18, Pest PHP.

---

## File map

- Create `packages/core/src/Runner/WorkflowEngine.php`: canonical transition evaluation.
- Create `packages/core/src/Runner/Dto/ExecutionState.php`: immutable serializable state.
- Create `packages/core/src/Runner/Dto/Transition.php`: next/retry/goto/end/suspend decisions.
- Modify `packages/core/src/Runner/WorkflowExecutor.php`: synchronous adapter.
- Modify `packages/core/src/Runner/StepExecutionWorker.php`: queue adapter.
- Modify `packages/core/src/Runner/WorkflowContext.php`: state compatibility facade or replace with `ExecutionState`.
- Modify `packages/core/src/Runner/StepOutcomeHandler.php`: remove infrastructure side effects.
- Modify `packages/core/src/Runner/StepOutcomeHandler.php` dependencies: use Plan 2/3 interfaces.
- Modify `packages/core/src/Runner/StepExecutor.php`: return a step outcome without choosing workflow control flow.
- Modify `packages/core/src/Runner/SubWorkflowInvoker.php`: call the canonical engine.
- Modify `packages/core/src/Runner/Dto/ExecutionResult.php` and `StepResult.php`: final result shape.
- Modify `packages/core/tests/Runner/WorkflowExecutorTest.php`, `StepOutcomeHandlerTest.php`, `StepExecutionWorkerTest.php`, and related event tests.
- Create `packages/core/tests/Runner/WorkflowEngineTest.php` and `ExecutionStateTest.php`.

### Task 1: Define state and transitions

- [ ] Write failing tests for a state containing execution ID, workflow ID, current step, inputs, attempts, step results, dependencies, and outputs in `packages/core/tests/Runner/ExecutionStateTest.php`.
- [ ] Write failing tests for `next`, `retry`, `goto`, `end`, and `suspend` transition objects in `packages/core/tests/Runner/WorkflowEngineTest.php`.
- [ ] Run `cd packages/core && vendor/bin/pest tests/Runner/ExecutionStateTest.php tests/Runner/WorkflowEngineTest.php`; expect missing classes/failures.
- [ ] Create immutable DTOs with named constructors and explicit serialized fields in `packages/core/src/Runner/Dto/ExecutionState.php` and `Transition.php`.
- [ ] Add round-trip serialization tests and implementation for all state fields, preserving `null`, `false`, `0`, empty arrays, and errors.
- [ ] Run the two focused test files; expect PASS.
- [ ] Commit `feat: define canonical execution state and transitions`.

### Task 2: Move action decisions into a pure engine

- [ ] Add failing tests for default actions, step actions, retry exhaustion, goto targets, end status, invoke results, and failed criteria in `WorkflowEngineTest.php`.
- [ ] Extract the decision logic from `StepOutcomeHandler.php` into `WorkflowEngine.php`; the engine may call expression, selector, and sub-workflow interfaces but must not dispatch queues, save state, acquire locks, or emit framework events.
- [ ] Return transitions instead of invoking `QueueDriverInterface`, `StateStoreInterface`, `Engine`, or `ExecutionRegistryInterface` from decision code.
- [ ] Preserve action ordering: first matching action wins; exhausted retry falls through to later actions; absent matching failure action terminates as failed.
- [ ] Update `StepOutcomeHandlerTest.php` to test returned transitions or remove the class when no consumer remains.
- [ ] Run `cd packages/core && vendor/bin/pest tests/Runner/WorkflowEngineTest.php tests/Runner/StepOutcomeHandlerTest.php`; expect PASS.
- [ ] Commit `refactor: make workflow control-flow decisions pure`.

### Task 3: Add the shared step budget and workflow call stack

- [ ] Write failing tests for a bounded goto loop, retry attempts, nested workflow calls, workflow cycles, and maximum workflow depth.
- [ ] Add `stepAttempts`, `maxSteps`, `workflowCallStack`, and `maxWorkflowDepth` to `ExecutionState` with getters and immutable update methods.
- [ ] Create typed exceptions under `packages/core/src/Runner/Exceptions/` for budget, workflow cycle, and workflow depth failures, extending the existing Arazzo exception hierarchy.
- [ ] Spend one shared budget unit before every step attempt, including retry and nested workflow attempts; do not reset the budget in a sub-workflow.
- [ ] Persist budget and call-stack fields in the queue state payload.
- [ ] Replace the older standalone `StepBudgetExceededException` plan behavior with this shared state implementation.
- [ ] Run focused budget and graph tests; expect PASS.
- [ ] Commit `feat: bound workflow execution with shared budgets`.

### Task 4: Implement the synchronous adapter

- [ ] Add failing integration tests proving the synchronous adapter executes linear steps, goto, retry, end, invoke, dependsOn, and workflow outputs.
- [ ] Rewrite `WorkflowExecutor.php` to create `ExecutionState` and loop over `WorkflowEngine::transition()` until terminal/suspended state.
- [ ] Remove topological-order-only execution from the public synchronous path; dependency ordering must be interpreted by the canonical engine.
- [ ] Inject clock and sleep interfaces so retries are deterministic in tests.
- [ ] Preserve event dispatching at adapter boundaries and include final outputs/errors in `ExecutionResult`.
- [ ] Update `WorkflowExecutorEventsTest.php` and `WorkflowExecutorTest.php` for the new result contract.
- [ ] Run `cd packages/core && vendor/bin/pest tests/Runner/WorkflowExecutorTest.php tests/Runner/WorkflowExecutorEventsTest.php`; expect PASS.
- [ ] Commit `feat: run workflows through canonical synchronous engine`.

### Task 5: Implement the queue adapter

- [ ] Add failing tests proving a persisted state resumes at the next transition without repeating completed steps.
- [ ] Rewrite `StepExecutionWorker.php` to load state, apply one canonical transition, persist the new state, and enqueue the next transition.
- [ ] Keep lock acquisition, event ledger writes, execution registry writes, and queue dispatch in the adapter only.
- [ ] Update `StepOutcomeHandlerEventsTest.php`, `StepExecutionWorkerTest.php`, and serialization fixtures.
- [ ] Run `cd packages/core && vendor/bin/pest tests/Runner/StepExecutionWorkerTest.php tests/Runner/StepOutcomeHandlerEventsTest.php`; expect PASS.
- [ ] Commit `refactor: adapt queued execution to canonical transitions`.

### Task 6: Reconnect Laravel and sub-workflow adapters

- [ ] Add failing Laravel binding tests for the canonical engine, synchronous runner, queue adapter, and OpenAPI/runtime collaborators.
- [ ] Update `packages/laravel/src/LaravelArazzoServiceProvider.php` to bind new interfaces without embedding transition logic.
- [ ] Update `SubWorkflowInvoker.php` to invoke the canonical engine and record nested results in state.
- [ ] Update `packages/laravel/tests/LaravelArazzoServiceProviderBindingsTest.php` and queue job tests.
- [ ] Run `cd packages/laravel && vendor/bin/pest`; expect PASS.
- [ ] Commit `refactor: wire Laravel adapters to canonical execution core`.

### Task 7: Remove obsolete duplicate paths

- [ ] Search for direct control-flow side effects with `rg -n 'queueDriver|stateStore|engine->evaluate|executionRegistry' packages/core/src/Runner` and confirm only adapters contain them.
- [ ] Delete obsolete classes/interfaces only after all references are removed; update Composer autoload and tests.
- [ ] Run `composer run analyse` and `composer run test`.
- [ ] Commit `refactor: remove duplicate workflow control-flow paths`.
