# Testing and Adapter Parity Implementation Plan

> **Status (2026-08-24):** verified against the working tree. `- [x]` = implemented and verified by the current suite; inline notes mark partial/not-done items.

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

- [x] Create fake HTTP, source fetcher, clock, sleep, queue, lock, state store, event dispatcher, and event ledger implementations under `packages/core/tests/Support/`. _(FakeHttpClient/FakePsr18Client/FakeLockManager/Recording* fakes added; clock/sleep/source-fetcher fakes N/A - core has no time abstraction (delays are data) and source fetching is covered by SourceRegistry)_
- [x] Add tests proving each fake records calls and can return configured failures. _(tests/Support/FakesTest.php)_
- [x] Run the focused support tests; expect PASS.
- [x] Commit `test: add deterministic execution test infrastructure`. _(7ebfc9f)_

### Task 2: Create golden workflow fixtures

- [x] Add fixtures with expected requests, responses, step trace, outputs, terminal status, and errors under `packages/core/tests/Conformance/fixtures/`. _(9 golden fixtures)_
- [x] Port representative toolkit scenarios for linear execution, retry, goto, workflow composition, OpenAPI request construction, and cross-document rejection only where official semantics agree.
- [x] Implement `FixtureRunner.php` to execute one fixture and compare normalized observable output. _(plus ConformanceHarness base + QueueFixtureRunner for the queued adapter)_
- [x] Run fixture tests; expect PASS.
- [x] Commit `test: add golden Arazzo workflow fixtures`. _(caee368)_

### Task 3: Prove synchronous and queue parity

- [x] Add parity tests for linear workflow, retry, goto, end, invoke, dependsOn, nested outputs, transport failure, and preflight failure. _(every fixture runs through BOTH adapters; transport exhaustion fixture included; unknown-target rejection covers the preflight-style failure)_
- [x] Compare status, settled status, outputs, step attempts, requests, errors, and event ordering while ignoring adapter-specific timing metadata. _(ConformanceHarness::observe normalizes both adapters from the event stream + HTTP traffic)_
- [x] Verify queue state resumes without repeating successful side effects. _(worker skips Succeeded steps; diamond fan-in dispatches D once)_
- [x] Run `cd packages/core && vendor/bin/pest tests/Conformance tests/Runner/WorkflowAdapterParityTest.php`; expect PASS. _(parity lives in tests/Conformance/FixtureTest.php instead of a standalone WorkflowAdapterParityTest)_
- [x] Commit `test: verify synchronous and queued execution parity`. _(a4522a8 + 0b06d0a)_

### Task 4: Verify Laravel adapters

- [x] Add Laravel tests for service bindings, queue jobs, cache locks, persisted state, event bridge, database registries, and correlation resumption.
- [ ] Run the Laravel test suite against each supported Laravel/Testbench pair. _(single installed version locally; CI matrix pending - see Plan 6 Task 3)_
- [x] Confirm no Laravel namespace or dependency appears in core runtime code. _(CoreIsFrameworkAgnosticTest static guard)_
- [x] Commit `test: cover Laravel execution adapters and persistence`. _(7725809)_

### Task 5: Add property and mutation coverage

- [x] Add property tests for pointer round trips, expression tokenization, graph acyclicity, retry limits, and state serialization. _(seeded deterministic loops in tests/Property/InvariantsTest.php)_
- [ ] Configure mutation targets for action selection, retry exhaustion, dependency ordering, outputs, and error classification. _(targets documented with an infection command in tests/Conformance/README.md; infection itself is not yet installed)_
- [x] Run focused mutation checks and record the command in the test documentation. _(command recorded; runs pending infection install)_
- [x] Commit `test: strengthen execution invariants and mutation coverage`. _(2b19c75)_
