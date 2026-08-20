# Testing and Adapter Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prove equivalent behavior across synchronous, queued, and Laravel adapters using shared fixtures and deterministic infrastructure.

**Architecture:** Tests drive the same fixture through each adapter with fake HTTP, clock, sleep, source, persistence, lock, queue, and event implementations. Toolkit fixtures may be ported as reference data but PHP tests remain independent.

**Tech Stack:** Pest PHP, PHPStan, Laravel Testbench, existing mock/fake support.

---

## File map

- Create `packages/core/tests/Conformance/FixtureRunner.php` and fixture assertions.
- Create `packages/core/tests/Conformance/fixtures/` for golden Arazzo/OpenAPI inputs and expected results.
- Create or extend `packages/core/tests/Runner/WorkflowAdapterParityTest.php`.
- Modify `packages/core/tests/Runner/*`, `packages/core/tests/Parser/*`, and `packages/core/tests/Validator/*` for regressions.
- Modify `packages/laravel/tests/Queue/*`, `Events/*`, `Persistence/*`, `State/*`, and service-provider tests.
- Modify `.github/workflows/ci.yml`, `Makefile`, and test configuration only after behavior tests exist.

### Task 1: Add deterministic test infrastructure

- [ ] Create fake HTTP, source fetcher, clock, sleep, queue, lock, state store, event dispatcher, and event ledger implementations under `packages/core/tests/Support/`.
- [ ] Add tests proving each fake records calls and can return configured failures.
- [ ] Run the focused support tests; expect PASS.
- [ ] Commit `test: add deterministic execution test infrastructure`.

### Task 2: Create golden workflow fixtures

- [ ] Add fixtures with expected requests, responses, step trace, outputs, terminal status, and errors under `packages/core/tests/Conformance/fixtures/`.
- [ ] Port representative toolkit scenarios for linear execution, retry, goto, workflow composition, OpenAPI request construction, and cross-document rejection only where official semantics agree.
- [ ] Implement `FixtureRunner.php` to execute one fixture and compare normalized observable output.
- [ ] Run fixture tests; expect PASS.
- [ ] Commit `test: add golden Arazzo workflow fixtures`.

### Task 3: Prove synchronous and queue parity

- [ ] Add parity tests for linear workflow, retry, goto, end, invoke, dependsOn, nested outputs, transport failure, and preflight failure.
- [ ] Compare status, settled status, outputs, step attempts, requests, errors, and event ordering while ignoring adapter-specific timing metadata.
- [ ] Verify queue state resumes without repeating successful side effects.
- [ ] Run `cd packages/core && vendor/bin/pest tests/Conformance tests/Runner/WorkflowAdapterParityTest.php`; expect PASS.
- [ ] Commit `test: verify synchronous and queued execution parity`.

### Task 4: Verify Laravel adapters

- [ ] Add Laravel tests for service bindings, queue jobs, cache locks, persisted state, event bridge, database registries, and correlation resumption.
- [ ] Run the Laravel test suite against each supported Laravel/Testbench pair.
- [ ] Confirm no Laravel namespace or dependency appears in core runtime code.
- [ ] Commit `test: cover Laravel execution adapters and persistence`.

### Task 5: Add property and mutation coverage

- [ ] Add property tests for pointer round trips, expression tokenization, graph acyclicity, retry limits, and state serialization.
- [ ] Configure mutation targets for action selection, retry exhaustion, dependency ordering, outputs, and error classification.
- [ ] Run focused mutation checks and record the command in the test documentation.
- [ ] Commit `test: strengthen execution invariants and mutation coverage`.
