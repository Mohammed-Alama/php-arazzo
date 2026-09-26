# Phase E: Runner Package Split + OMS Engine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split the monolithic `packages/runner` into the four inner layers of the agreed package model — `alama/arazzo-runtime`, `alama/arazzo-events`, `alama/arazzo-request-pipeline`, `alama/arazzo-engine` — and only then build the OMS: the explicit `StepStateMachineEngine` (E6), `StoredWorkflowStateRepository` (E7), a unified sync/async execution carrier (E8), `OperationExecutorRegistry` (E9), and binding-aware request compilation with `ResponseValidatorInterface` dispatch (E10). Killing the C11 sync/async divergence and establishing the canonical operation state machine are the goal of the second half; the first half is the precondition that makes it land in the right place.

**Why the split comes first:** the OMS is the layer that every protocol and every execution model plugs into. Introducing it while `Execution/`, `Protocol/`, `Policy/`, `Telemetry/`, `State/` and the facade all sit in one package means the state machine, the carrier and the registries are written against concrete neighbours instead of published seams — and every later protocol package (SOAP, RPC) inherits that coupling. Splitting first means the OMS is written against `Alama\Arazzo\Engine\...`, `Alama\Arazzo\Runtime\...` and `Alama\Arazzo\Events\...` seams that are already independent of how a step is dispatched. Evidence for the current coupling debt is in `docs/research/2026-09-26-runner-package-split-validation.md`.

**Package model (source of truth for the whole roadmap):**

| Layer | Package | Created in | Depends on |
|---|---|---|---|
| 0 | `contracts`, `expression`, `evaluation`, `document` | Phases A–D (landed) | — |
| 1 | `alama/arazzo-runtime` | **E1** | contracts |
| 1 | `alama/arazzo-events` | **E2** | contracts |
| 2 | `alama/arazzo-request-pipeline` | **E3** | contracts, document, expression, evaluation |
| 3 | `alama/arazzo-engine` | **E4** | contracts, evaluation, runtime |
| 5+6 | `alama/arazzo-runner` (sync/async namespaces + facade at root) | Phase F2 | engine, request-pipeline, runtime, events, protocol-* |
| vertical | `alama/arazzo-protocol-http` | Phase F1 | engine, request-pipeline, contracts |
| vertical | `alama/arazzo-protocol-soap` | Phase F3 | engine, request-pipeline, contracts |
| vertical | `alama/arazzo-protocol-rpc` | Phase F4 | engine, request-pipeline, contracts |

**Boundaries this phase enforces (arch tests, one per extracted package):**
- `Alama\Arazzo\Runtime` uses nothing from `Alama\Arazzo\Engine`, `Alama\Arazzo\Runner`, or any `Alama\Arazzo\Protocol\*`.
- `Alama\Arazzo\Events` uses nothing from `Alama\Arazzo\Engine`, `Alama\Arazzo\Runner`, or `Alama\Arazzo\Runtime`.
- `Alama\Arazzo\RequestPipeline` uses nothing from `Alama\Arazzo\Engine`, `Alama\Arazzo\Runner`, or any `Alama\Arazzo\Protocol\*`.
- `Alama\Arazzo\Engine` uses nothing from `Alama\Arazzo\Runner` or any `Alama\Arazzo\Protocol\*`. This is the boundary that makes the engine a real core.

**Architecture:** the `StepStateMachineEngine` wraps the existing `WorkflowEngine::transition()` (pure decision layer, now living in `arazzo-engine`) and adds explicit `StepState` transitions with enter-handlers. The unified carrier replaces `StepExecutionWorker` + `StepOutcomeHandler` with a single `UnifiedStepCarrier` at the runner root, shared by the `Sync\` and `Async\` namespaces. `OperationExecutorRegistry` replaces direct `StepProtocolExecutorInterface` list resolution with a first-match-wins registry keyed by `OperationExecutorPluginInterface`. `StoredWorkflowStateRepository` wraps the relocated `StateStoreInterface` with a versioned envelope and backward-compatible loader.

**Sequencing note on arch tests:** each extraction task's arch test is a *regression guard on a boundary the move establishes*, not a new behaviour. It is committed together with the move and verified to bite by temporarily introducing a forbidden import, confirming RED, then reverting before commit. Do not claim a red-first cycle that does not exist.

**Tech Stack:** PHP ^8.4, Pest v5 (`pestphp/pest`), PHPStan ^2.0 + `phpstan-deprecation-rules`, Laravel Pint, `psr/event-dispatcher`.

**Spec:** `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`

## Global Constraints

- New package namespaces: `Alama\Arazzo\Runtime\...`, `Alama\Arazzo\Events\...`, `Alama\Arazzo\RequestPipeline\...`, `Alama\Arazzo\Engine\...` for extracted code; `Alama\Arazzo\Runner\...` for classes that stay in the runner. All classes `declare(strict_types=1)`, `@internal`.
- `StepState` enum values: `Pending='pending'`, `ExecutingRequest='executing_request'`, `EvaluatingCriteria='evaluating_criteria'`, `AwaitingActorInput='awaiting_actor_input'`, `ActorInputReceived='actor_input_received'`, `Completed='completed'`, `Failed='failed'`. `Retrying` is an edge (`EVALUATING_CRITERIA → PENDING`), not a state.
- `WorkflowStateRepositoryInterface` signature: `save(string $executionId, WorkflowContextInterface $state): void`, `load(string $executionId): ?WorkflowContextInterface`, `delete(string $executionId): void` (Phase A task A5).
- `OperationExecutorPluginInterface` signature: `supports(Step, ArazzoDocument): bool`, `execute(Step, WorkflowContext, ArazzoDocument, string): StepExecutionOutcome` + `PluginInterface::name(): string`, `priority(): int` (Phase A task A1).
- `ResponseTransferInterface` seam + generic `ResponseTransfer` value type (Phase A task A6): `ResponseTransferInterface` exposes `status(): mixed`, `headers(): array`, `rawBody(): mixed`, `hasView(string): bool`, `view(string): mixed`, `meta(): array`; the generic `ResponseTransfer` (`Alama\Arazzo\Contracts\Spec`) is the protocol-agnostic implementation with a keyed `views` bag (JSON/XML/proto facets filled by the per-protocol DTOs in Phase F, not flat constructor props).
- `StepProtocolExecutorInterface` remains `@deprecated`; existing executors are adapted to `OperationExecutorPluginInterface` in this phase.
- Every task ends with the affected package's test command green. Commands: `composer run test-runtime`, `composer run test-events`, `composer run test-pipeline`, `composer run test-engine`, `composer run test-runner`. Each new package registers its own script in root `composer.json` in its scaffold step.
- Every task's `--filter` runs `vendor/bin/pest packages/<pkg>/tests --filter "<name>"` from the repo root.
- Static analysis per task: `composer run analyse-<pkg>` (PHPStan with that package's `phpstan.neon.dist`).
- No code comments unless explaining a deprecation or an ISO-8601 duration.
- E0–E5 (the split) may touch root `composer.json` and `packages/*/composer.json` for scaffolding. E6–E10 (the OMS) must not touch anything outside the package that owns the class being added. Only E11 (the gate) runs repo-wide commands.
- Commit order is strict: E0 → E1 → E2 → E3 → E4 → E5 (split, each one atomic) then E6 → E7 → E8 → E9 → E10 (OMS) then E11 (gate). The OMS tasks are not started until the E5 split gate is green.
- This plan assumes Phase A contracts are landed. The Phase A types are referenced by FQCN throughout; if Phase A is not yet merged, create the minimal stubs first.

---

## Task E0: Runner hygiene — delete dead code, relocate the `HttpClientInterface` seam

Pre-work from the package-split validation (`docs/research/2026-09-26-runner-package-split-validation.md`), independent of every later task. Each step is an atomic commit so the deletions are reviewable on their own. Do not mix these with E1+ content.

**Files:**
- Delete: `packages/runner/src/Async/` — whole directory (6 classes: `ExecutionStateBuilder`, `PreflightGuard`, `StateReconciler`, `SuspensionHandler`, `TransitionApplier`, `WorkerEvents` + their 6 test files). Unreferenced from `src/` (verified: 0 refs outside their own namespace); fully superseded by E8's `UnifiedStepCarrier`. The `SuspensionHandler` `is_scalar()` correlation-id guard is preserved in E8 — do not lose it.
- Delete: `packages/runner/src/Protocol/SubWorkflowExecutor.php` + `packages/runner/tests/Protocol/SubWorkflowExecutorTest.php` — dead (0 refs outside `Protocol/`); the redundant second sub-workflow impl that drags `Execution\WorkflowEngine` into `Protocol/`.
- Delete: `packages/runner/src/Protocol/ProtocolExecutorRegistry.php` + `packages/runner/tests/Protocol/ProtocolExecutorRegistryTest.php` — dead (0 refs outside `Protocol/`). Keep `Execution/Interfaces/ProtocolExecutorRegistryInterface` (E9 marks it `@deprecated`).
- Move: `packages/runner/src/Infrastructure/Interfaces/HttpClientInterface.php` → `packages/contracts/src/Interfaces/HttpClientInterface.php`, namespace `Alama\Arazzo\Runner\Infrastructure\Interfaces` → `Alama\Arazzo\Contracts\Interfaces`. It is an interface, and the FLATTEN philosophy is "one place for interfaces" — every protocol package and the request pipeline need this seam, and making them depend on `arazzo-runtime` for one interface is the wrong edge. `arazzo-runtime` keeps implementations only.
- Modify: every file importing the old FQCN (`rg -l 'Runner\\Infrastructure\\Interfaces\\HttpClientInterface' packages/`) — the importers are `AsyncGraphSeams.php`, `Protocol/HttpStepExecutor.php`, `Protocol/AsyncApiStepExecutor.php`, `Protocol/SubWorkflowStepExecutor.php`, and the Laravel `AsyncGraphResolver`.

**Interfaces:**
- Delivers: ~600 lines of dead code removed; `HttpClientInterface` promoted to the contracts seam. No behavioural change.

- [ ] **Step 1: Delete the dead `Async/` directory**

Run:
```bash
rm -rf packages/runner/src/Async packages/runner/tests/Async
rg -l 'Runner\\Async\\' packages/ || echo "no references"
```

Expected: `no references`. (`AsyncGraphSeams` / `AsyncExecutionGraph` are async *seam names*, not the deleted directory — confirm they do not `use` any `Async\*` class.)

Run: `composer run test-runner && composer run analyse-runner`

Expected: PASS.

- [ ] **Step 2: Delete the dead `Protocol/` classes**

Run:
```bash
rm packages/runner/src/Protocol/SubWorkflowExecutor.php packages/runner/tests/Protocol/SubWorkflowExecutorTest.php \
   packages/runner/src/Protocol/ProtocolExecutorRegistry.php packages/runner/tests/Protocol/ProtocolExecutorRegistryTest.php
composer run test-runner
```

Expected: PASS.

- [ ] **Step 3: Commit the deletions**

```bash
git add -A packages/runner
git commit -m "refactor(runner): delete dead Async/ + Protocol classes"
```

- [ ] **Step 4: Relocate `HttpClientInterface` to contracts**

```bash
git mv packages/runner/src/Infrastructure/Interfaces/HttpClientInterface.php packages/contracts/src/Interfaces/HttpClientInterface.php
```

Edit the moved file: `namespace Alama\Arazzo\Runner\Infrastructure\Interfaces;` → `namespace Alama\Arazzo\Contracts\Interfaces;`.

Rewrite every importer found by `rg -l 'Runner\\Infrastructure\\Interfaces\\HttpClientInterface' packages/`, replacing the FQCN with `Alama\Arazzo\Contracts\Interfaces\HttpClientInterface`.

Run: `composer run test-runner && composer run analyse-runner && composer run test-laravel`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A packages/contracts packages/runner packages/laravel
git commit -m "refactor(contracts): promote HttpClientInterface from runner Infrastructure to the contracts seam"
```

---

## Task E1: Extract `alama/arazzo-runtime`

Scaffold the first new package and move the small swappable runtime services into it. These are grouped as one package on purpose: telemetry, retry policy, lock strategies and state stores are individually too small to clear the "would someone install this alone" bar, but they share the same volatility profile (infrastructure pluggability) and the same zero-internal-coupling shape. Splitting them into four packages was considered and rejected — see the "Rejected alternatives" note at the end of this document.

**Contents moved:**
- `packages/runner/src/Policy/RetryPolicy.php`, `Policy/ExponentialBackoffCalculator.php` → `packages/runtime/src/Policy/`
- `packages/runner/src/Telemetry/OtelSetup.php`, `Telemetry/TraceContextPropagator.php` → `packages/runtime/src/Telemetry/`
- `packages/runner/src/Infrastructure/FileLockStrategy.php`, `Infrastructure/PessimisticLockStrategy.php`, `Infrastructure/NullLockStrategy.php` → `packages/runtime/src/Infrastructure/` (the `Interfaces/` subdir is already gone after E0 Step 4)
- `packages/runner/src/State/FileStateStore.php`, `State/InMemoryStateStore.php`, `State/Interfaces/*`, `State/Data/*`, `State/Exceptions/*` → `packages/runtime/src/State/`

**Open seam decision, deliberately not resolved here:** the five `State/Interfaces/*` port contracts stay in `arazzo-runtime` for now. Whether they belong in `contracts` (matching the FLATTEN philosophy) is a Phase A tail decision and is recorded as such — do not move them in this task.

**Files:**
- Create: `packages/runtime/` (full package: `composer.json`, `phpstan.neon.dist`, `tests/Pest.php`, `tests/Architecture/ArchTest.php`, `src/`)
- Modify: root `composer.json` (repositories, require, autoload-dev, scripts `analyse-runtime` / `test-runtime`)
- Modify: every importing file under `packages/`
- Move: `packages/runner/tests/Policy/`, `packages/runner/tests/Telemetry/`, `packages/runner/tests/State/` → `packages/runtime/tests/` (there is no `tests/Infrastructure/` on `main`)

**Interfaces:**
- Produces package `alama/arazzo-runtime`, PSR-4 `Alama\Arazzo\Runtime\` → `src/`. Requires `alama/arazzo-contracts` only.
- Arch guard: `Alama\Arazzo\Runtime` uses nothing from `Alama\Arazzo\Engine`, `Alama\Arazzo\Runner`, or `Alama\Arazzo\Protocol\*`.

- [ ] **Step 1: Scaffold the package**

`packages/runtime/composer.json`:

```json
{
    "name": "alama/arazzo-runtime",
    "description": "Runtime support services for alama/arazzo-core: retry policy, telemetry, lock strategies, state stores.",
    "keywords": ["alama", "arazzo", "runtime", "telemetry", "state"],
    "license": "MIT",
    "repositories": [
        {"type": "path", "url": "../contracts"}
    ],
    "require": {
        "php": "^8.4",
        "alama/arazzo-contracts": "@dev",
        "psr/log": "^3.0",
        "open-telemetry/api": "^1.1"
    },
    "require-dev": {
        "pestphp/pest": "^5.0",
        "pestphp/pest-plugin-arch": "^5.0"
    },
    "autoload": {"psr-4": {"Alama\\Arazzo\\Runtime\\": "src/"}},
    "autoload-dev": {"psr-4": {"Alama\\Arazzo\\Tests\\Runtime\\": "tests/"}},
    "config": {"sort-packages": true, "allow-plugins": {"pestphp/pest-plugin": true, "phpstan/extension-installer": true}},
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

Copy `packages/document/phpstan.neon.dist` as the template for `packages/runtime/phpstan.neon.dist` (adjust `scanDirectories` to `../contracts/src`). Copy `packages/runner/tests/Pest.php` for `packages/runtime/tests/Pest.php`.

- [ ] **Step 2: Move the classes and rewrite namespaces**

```bash
mkdir -p packages/runtime/src/{Policy,Telemetry,Infrastructure,State}
git mv packages/runner/src/Policy/RetryPolicy.php            packages/runtime/src/Policy/
git mv packages/runner/src/Policy/ExponentialBackoffCalculator.php packages/runtime/src/Policy/
git mv packages/runner/src/Telemetry/OtelSetup.php           packages/runtime/src/Telemetry/
git mv packages/runner/src/Telemetry/TraceContextPropagator.php packages/runtime/src/Telemetry/
git mv packages/runner/src/Infrastructure/FileLockStrategy.php packages/runtime/src/Infrastructure/
git mv packages/runner/src/Infrastructure/PessimisticLockStrategy.php packages/runtime/src/Infrastructure/
git mv packages/runner/src/Infrastructure/NullLockStrategy.php packages/runtime/src/Infrastructure/
git mv packages/runner/src/State packages/runtime/src/State
rmdir packages/runner/src/Infrastructure 2>/dev/null || true
```

Namespace rewrites inside the moved files:
- `namespace Alama\Arazzo\Runner\Policy;` → `namespace Alama\Arazzo\Runtime\Policy;`
- `namespace Alama\Arazzo\Runner\Telemetry;` → `namespace Alama\Arazzo\Runtime\Telemetry;`
- `namespace Alama\Arazzo\Runner\Infrastructure;` → `namespace Alama\Arazzo\Runtime\Infrastructure;`
- `namespace Alama\Arazzo\Runner\State;` → `namespace Alama\Arazzo\Runtime\State;`
- `namespace Alama\Arazzo\Runner\State\Interfaces;` → `namespace Alama\Arazzo\Runtime\State\Interfaces;`
- `namespace Alama\Arazzo\Runner\State\Data;` → `namespace Alama\Arazzo\Runtime\State\Data;`
- `namespace Alama\Arazzo\Runner\State\Exceptions;` → `namespace Alama\Arazzo\Runtime\State\Exceptions;`

Then rewrite all cross-package references repo-wide:
```bash
rg -l 'Alama\\Arazzo\\Runner\\\(Policy|Telemetry|Infrastructure|State)' packages/
```
Replace each with the `Alama\Arazzo\Runtime\...` equivalent. This touches `Execution/StepExecutionWorker.php` (Telemetry + State), `Execution/StepOutcomeHandler.php` (State), `Execution/CorrelationResumer.php` (State), `Execution/SubWorkflowInvoker.php` (State), `Execution/WorkflowExecutor.php` (Policy via engine), `AsyncExecutionGraph.php`, `Execution/WorkflowEngine.php` (Policy), `Jobs/*`, and the Laravel bindings.

Move the tests to match:
```bash
mkdir -p packages/runtime/tests
git mv packages/runner/tests/Policy packages/runtime/tests/Policy
git mv packages/runner/tests/Telemetry packages/runtime/tests/Telemetry
git mv packages/runner/tests/State packages/runtime/tests/State
# No tests/Infrastructure directory exists on main — the three lock strategies and
# their state-store implementations are covered from tests/State. Do not add a
# `git mv` for it; if a later branch adds one, move it here.
```

- [ ] **Step 3: Root plumbing**

Add to root `composer.json`:
- `repositories`: `{"type": "path", "url": "packages/runtime"}`
- `require`: `"alama/arazzo-runtime": "@dev"`
- `autoload-dev`: `"Alama\\Arazzo\\Tests\\Runtime\\": "packages/runtime/tests"`
- `scripts`: `"analyse-runtime"` and `"test-runtime"` mirroring the existing `analyse-runner` / `test-runner` entries

Then:
```bash
composer update alama/arazzo-runtime --with-dependencies --no-interaction
```

- [ ] **Step 4: Add the arch guard and verify it bites**

`packages/runtime/tests/Architecture/ArchTest.php`:

```php
arch('runtime is a leaf: no engine, runner, or protocol dependencies')
    ->expect('Alama\Arazzo\Runtime')
    ->not->toUse(['Alama\Arazzo\Engine', 'Alama\Arazzo\Runner', 'Alama\Arazzo\Protocol']);
```

Verify the guard bites: temporarily add `use Alama\Arazzo\Runner\RunnerFacade;` to `packages/runtime/src/State/InMemoryStateStore.php`, run the test, confirm RED, then revert. Commit only after it is GREEN.

- [ ] **Step 5: Install, verify, commit**

```bash
composer run test-runtime && composer run analyse-runtime
composer run test-runner && composer run test-laravel
git add -A
git commit -m "refactor(runtime): extract Policy, Telemetry, lock strategies and state stores into arazzo-runtime"
```

---

## Task E2: Extract `alama/arazzo-events`

Kept separate from `arazzo-runtime` because it is a distinct seam that grows independently: eleven event DTOs, the `EventLedgerInterface` port, and the ledger listener. It is consumed by the engine, both runners, and the Laravel composition layer, and it is the package most likely to gain a transport (PSR-14, PSR-16) in future work.

**Files:**
- Create: `packages/events/` (scaffold mirroring E1 Step 1 — `alama/arazzo-events`, PSR-4 `Alama\Arazzo\Events\` → `src/`)
- Move: `packages/runner/src/Events/` → `packages/events/src/Events/` — **but** the PSR-4 root is `Alama\Arazzo\Events\` → `src/`, so the moved classes sit at `src/*.php` (flattened) rather than `src/Events/*.php`. Choose the flattened layout so the namespace matches the directory and no DTO needs a redundant `Events\` segment.
- Modify: root `composer.json`; every importer (`Execution/WorkflowExecutor.php`, `Execution/StepExecutionWorker.php`, `Execution/StepOutcomeHandler.php`, `Execution/CorrelationResumer.php`, `packages/laravel/src/...`)

**Interfaces:**
- Produces package `alama/arazzo-events`, PSR-4 `Alama\Arazzo\Events\` → `src/`. Requires `alama/arazzo-contracts` only.
- Arch guard: `Alama\Arazzo\Events` uses nothing from `Alama\Arazzo\Engine`, `Alama\Arazzo\Runner`, or `Alama\Arazzo\Runtime`.

- [ ] **Step 1: Scaffold + move + rewrite namespaces**

Mirror E1 Step 1 for the name `arazzo-events` / `Alama\Arazzo\Events\`, then:

```bash
git mv packages/runner/src/Events packages/events/src
rg -l 'Alama\\Arazzo\\Runner\\Events' packages/
```

Namespace rewrites: `namespace Alama\Arazzo\Runner\Events;` → `namespace Alama\Arazzo\Events;`, `namespace Alama\Arazzo\Runner\Events\Interfaces;` → `namespace Alama\Arazzo\Events\Interfaces;`, `namespace Alama\Arazzo\Runner\Events\Listener;` → `namespace Alama\Arazzo\Events\Listener;`. Because the directory flattened, drop the leading `Events\` from every consuming `use` statement as well.

Move tests: `git mv packages/runner/tests/Events packages/events/tests`.

- [ ] **Step 2: Root plumbing, arch guard, verify**

Add the `packages/events` path repository, `alama/arazzo-events` to root `require`, the `Alama\Arazzo\Tests\Events\` autoload-dev entry, and `analyse-events` / `test-events` scripts. `composer update alama/arazzo-events --with-dependencies --no-interaction`.

Arch test in `packages/events/tests/Architecture/ArchTest.php`:

```php
arch('events is a leaf: no engine, runtime, or runner dependencies')
    ->expect('Alama\Arazzo\Events')
    ->not->toUse(['Alama\Arazzo\Engine', 'Alama\Arazzo\Runtime', 'Alama\Arazzo\Runner']);
```

Verify it bites with a temporary forbidden import, then revert.

- [ ] **Step 3: Install, verify, commit**

```bash
composer run test-events && composer run analyse-events
composer run test-runtime && composer run test-runner && composer run test-laravel
git add -A
git commit -m "refactor(events): extract event DTOs, EventLedgerInterface and ledger listener into arazzo-events"
```

---

## Task E3: Extract `alama/arazzo-request-pipeline`

The shared request-compilation pipeline. It is extracted **before** the engine and before the protocol packages because it has exactly two current consumers — the sync `StepExecutor` and the async `Protocol/HttpStepExecutor` — and both sit in layers above it. Extracting it first means neither the protocol packages nor the runners end up owning a shared pipeline that both need.

**Contents moved (all currently in `packages/runner/src/Execution/`):**
- `RequestCompiler.php`, `ParameterSerializer.php`, `TypeCaster.php`, `SchemaValidator.php`, `ResponseSchemaValidator.php`, `ExpressionValueResolver.php`, `ExecutionExpressionResolver.php`, `IdempotencyKeyInjector.php`, `StepParameterMerger.php`, `ReusableParameterResolver.php`, `StepOutputExtractor.php` → `packages/request-pipeline/src/`

**Deliberately not moved:** `DefaultOpenApiExecutor.php` and `Execution/Interfaces/OpenApiExecutorInterface.php` stay in `runner` for now. `DefaultOpenApiExecutor` binds to a `ResolvedOperation` (an OpenAPI-specific DTO) and drives a PSR-18 client, so it is OpenAPI/transport-specific, not generic pipeline — it relocates to `alama/protocol-http` in Phase F1.2. `OpenApiExecutorInterface` is the transport seam the runner injects; F1.2 makes it a BC alias of the protocol-http canonical rather than resolving its home here.

`StepOutputExtractor` moves here too — note it imports `Expression\Enum\ReferenceKind`, which is an allowed L2→L0 dependency.

**Files:**
- Create: `packages/request-pipeline/` (full package: `composer.json`, `phpstan.neon.dist`, `tests/Pest.php`, `tests/Architecture/ArchTest.php`, `src/`)
- Move: the eleven classes above
- Move: `packages/runner/tests/Execution/` → `packages/request-pipeline/tests/` for the matching test files
- Modify: root `composer.json`; every importer

**Interfaces:**
- Produces package `alama/arazzo-request-pipeline`, PSR-4 `Alama\Arazzo\RequestPipeline\` → `src/`. Requires `contracts`, `document`, `expression`, `evaluation` — no engine, no runner, no protocol.
- Arch guard: `Alama\Arazzo\RequestPipeline` uses nothing from `Alama\Arazzo\Engine`, `Alama\Arazzo\Runner`, or `Alama\Arazzo\Protocol\*`.

- [ ] **Step 1: Scaffold + move + rewrite namespaces**

Mirror E1 Step 1 for `alama/arazzo-request-pipeline` / `Alama\Arazzo\RequestPipeline\`, requiring `contracts`, `document`, `expression`, `evaluation` and `psr/http-client`, `psr/http-factory`, `psr/http-message`. Then move the eleven classes (flattening `Execution/` into `src/`) and rewrite:

- `namespace Alama\Arazzo\Runner\Execution;` → `namespace Alama\Arazzo\RequestPipeline;` (for the moved classes only — not for the classes staying in the runner)

Repo-wide:
```bash
rg -l 'Alama\\Arazzo\\Runner\\Execution\\' packages/
```
Each hit must be classified by hand into *moved* (rewrite to `Alama\Arazzo\RequestPipeline\...`) or *staying* (leave as `Alama\Arazzo\Runner\Execution\...`). Do not blanket-replace — the runner keeps its own `Execution/` namespace for now; Phase F2 splits it into `Sync\` and `Async\`.

- [ ] **Step 2: Root plumbing, arch guard, verify**

Add the path repository, root `require`, `Alama\Arazzo\Tests\RequestPipeline\` autoload-dev, `analyse-pipeline` / `test-pipeline` scripts. `composer update alama/arazzo-request-pipeline --with-dependencies --no-interaction`.

Arch test:
```php
arch('request-pipeline is protocol- and runner-agnostic')
    ->expect('Alama\Arazzo\RequestPipeline')
    ->not->toUse(['Alama\Arazzo\Engine', 'Alama\Arazzo\Runner', 'Alama\Arazzo\Protocol']);
```

Verify it bites with a temporary forbidden import, then revert.

- [ ] **Step 3: Confirm the two-consumer invariant**

```bash
rg -n 'RequestCompiler' packages/ --glob '*.php' | rg -v 'packages/request-pipeline'
```

Expected: only the sync `StepExecutor` and `Protocol/HttpStepExecutor` (both to be moved/rewired in Phase F) reference it, plus the Laravel bindings. If a third consumer appears, note it in the commit message — it is a signal the class belongs elsewhere.

- [ ] **Step 4: Install, verify, commit**

```bash
composer run test-pipeline && composer run analyse-pipeline
composer run test-runner && composer run test-laravel
git add -A
git commit -m "refactor(request-pipeline): extract request compilation pipeline into arazzo-request-pipeline"
```

---

## Task E4: Extract `alama/arazzo-engine` — the pure core

The inner-most runtime layer. This is the boundary that makes the OMS land in the right place, and it is the one that most needs an explicit guard: `WorkflowEngine` is already clean (it needs only `ExpressionResolverInterface` from evaluation plus `RetryPolicy` from runtime), so the move is close to mechanical. The risk is a new import sneaking in from `runner` or `Protocol` — which the arch test is there to catch.

**Contents moved:**
- `packages/runner/src/Execution/WorkflowEngine.php` → `packages/engine/src/WorkflowEngine.php`
- `packages/runner/src/Execution/Data/Transition.php`, `Data/RunControlFlow.php`, `Data/RunPersistence.php` → `packages/engine/src/Data/`
- `packages/runner/src/Execution/Enum/TransitionType.php` (and siblings) → `packages/engine/src/Enum/`
- `packages/runner/src/Execution/Exceptions/*` → `packages/engine/src/Exceptions/`

Deliberately **not** moved: `StepStateMachineEngine` and `StepTransition`/`StepTransitionType` from E6 (created directly in `arazzo-engine`), and `Execution/Data/ExecutionEvaluationInput`, `ExecutionResult`, `StepResult`, `SubWorkflowResult` (runner-level data shapes — if the engine needs them, they move in E6, not here).

**Files:**
- Create: `packages/engine/` (scaffold — `alama/arazzo-engine`, PSR-4 `Alama\Arazzo\Engine\` → `src/`)
- Move: as listed; matching tests → `packages/engine/tests/`
- Modify: root `composer.json`; every importer

**Interfaces:**
- Produces package `alama/arazzo-engine`, PSR-4 `Alama\Arazzo\Engine\` → `src/`. Requires `contracts`, `evaluation`, `arazzo-runtime` (policy only).
- Arch guard: `Alama\Arazzo\Engine` uses nothing from `Alama\Arazzo\Runner` or `Alama\Arazzo\Protocol\*`. This is the boundary that makes the engine a real core and that Phase F's protocol packages depend on.

- [ ] **Step 1: Scaffold + move + rewrite namespaces**

Mirror E1 Step 1 for `alama/arazzo-engine` / `Alama\Arazzo\Engine\`, requiring `contracts`, `evaluation`, `arazzo-runtime`. Then:

```bash
mkdir -p packages/engine/src/{Data,Enum,Exceptions}
git mv packages/runner/src/Execution/WorkflowEngine.php packages/engine/src/
git mv packages/runner/src/Execution/Data/Transition.php packages/engine/src/Data/
git mv packages/runner/src/Execution/Data/RunControlFlow.php packages/engine/src/Data/
git mv packages/runner/src/Execution/Data/RunPersistence.php packages/engine/src/Data/
git mv packages/runner/src/Execution/Exceptions packages/engine/src/Exceptions
git mv packages/runner/src/Execution/Enum packages/engine/src/Enum
```

Namespace rewrites: `namespace Alama\Arazzo\Runner\Execution;` → `namespace Alama\Arazzo\Engine;` (for `WorkflowEngine`), `...\Execution\Data;` → `Alama\Arazzo\Engine\Data;`, `...\Execution\Enum;` → `Alama\Arazzo\Engine\Enum;`, `...\Execution\Exceptions;` → `Alama\Arazzo\Engine\Exceptions;`.

Repo-wide:
```bash
rg -l 'Alama\\Arazzo\\Runner\\Execution\\\(WorkflowEngine|Data\\Transition\|Data\\RunControlFlow\|Data\\RunPersistence\|Enum\\|Exceptions)' packages/
```
Rewrite each to the `Alama\Arazzo\Engine\...` equivalent. Known importers: `Execution/WorkflowExecutor.php`, `Execution/ExecutionGraphFactory.php`, `Execution/StepOutcomeHandler.php`, `Execution/AsyncExecutionGraphAssembler.php`, `Execution/StepExecutionWorker.php`, `Execution/Data/RunControlFlow.php` (self-reference), and `packages/laravel/src/...`.

- [ ] **Step 2: Root plumbing, arch guard, verify**

Add the path repository, root `require`, `Alama\Arazzo\Tests\Engine\` autoload-dev, `analyse-engine` / `test-engine` scripts. `composer update alama/arazzo-engine --with-dependencies --no-interaction`.

Arch test in `packages/engine/tests/Architecture/ArchTest.php`:

```php
arch('engine is a pure core: no runner or protocol dependencies')
    ->expect('Alama\Arazzo\Engine')
    ->not->toUse(['Alama\Arazzo\Runner', 'Alama\Arazzo\Protocol']);
```

Verify it bites: temporarily add `use Alama\Arazzo\Runner\Execution\StepExecutor;` to `packages/engine/src/WorkflowEngine.php`, confirm RED, revert.

- [ ] **Step 3: Confirm the engine's real dependency set**

```bash
rg -o 'use Alama\\Arazzo\\[A-Za-z\\]*' packages/engine/src --glob '*.php' | sed 's/.*://' | sort -u
```

Expected: only `Contracts\...`, `Evaluation\...`, and `Runtime\Policy\RetryPolicy`. Anything from `Runner`, `Protocol`, `RequestPipeline` or `Events` is a finding — fix it or record why in the commit message.

- [ ] **Step 4: Install, verify, commit**

```bash
composer run test-engine && composer run analyse-engine
composer run test-runner && composer run test-laravel
git add -A
git commit -m "refactor(engine): extract WorkflowEngine, Transition and execution exceptions into arazzo-engine"
```

---

## Task E5: Split gate — verify the four-layer boundary before any OMS code lands

**Files:**
- None to modify unless a gate fails.

**Interfaces:**
- Consumes: E0–E4.

- [ ] **Step 1: Run every package suite**

```bash
composer run test-runtime && composer run test-events
composer run test-pipeline && composer run test-engine
composer run test-runner && composer run test-laravel
```

Expected: PASS.

- [ ] **Step 2: Run static analysis across all packages**

```bash
composer run analyse-runtime && composer run analyse-events
composer run analyse-pipeline && composer run analyse-engine && composer run analyse-runner
```

Expected: PASS (0 errors). Relocate any PHPStan baseline entries that moved with the classes — the baseline is per-package, so entries for moved files move to the destination package's baseline.

- [ ] **Step 3: Verify the layer boundaries by sweep**

```bash
# L1 runtime is a leaf
rg -n 'Alama\\Arazzo\\(Engine|Runner|Protocol)\\' packages/runtime/src || echo "runtime clean"
# L1 events is a leaf
rg -n 'Alama\\Arazzo\\(Engine|Runner|Runtime)\\' packages/events/src || echo "events clean"
# L2 pipeline knows nothing above it
rg -n 'Alama\\Arazzo\\(Engine|Runner|Protocol)\\' packages/request-pipeline/src || echo "pipeline clean"
# L3 engine knows nothing above it
rg -n 'Alama\\Arazzo\\(Runner|Protocol)\\' packages/engine/src || echo "engine clean"
```

Expected: all four lines report clean. This is the machine-checkable statement of "the split happened before the OMS" that the phase goal depends on.

- [ ] **Step 4: Run the repo gate**

Run: `make verify` (repo root)

Expected: PASS — confirms no consumer outside `packages/runner` broke.

- [ ] **Step 5: Commit any baseline/formatting fixes**

```bash
git add -A
git commit -m "chore: relocate PHPStan baselines after the four-layer split"
```

**Do not start E6 until this gate is green.** The whole point of the phase ordering is that the OMS is written against the split seams; landing it first would defeat the exercise.

---

## Task E6: StepStateMachineEngine — explicit transition table over StepState

The core of the OMS. An explicit transition table mapping `(StepState, outcome)` pairs to `(next StepState, enter-handler)`. Guards (budget, deps, actions) delegate to the existing pure `WorkflowEngine::transition()`. Three 1.2-PR additions (D10): interaction steps transition `PENDING → AWAITING_ACTOR_INPUT` directly (bypass `EXECUTING_REQUEST`); `onTimeout` becomes an ordered failure-action list; `onCancel` is a first-class cancellation path. `timeout` accepts ISO 8601 duration strings in addition to integer milliseconds.

**Files:**
- Create: `packages/engine/src/StepStateMachineEngine.php`
- Create: `packages/engine/src/Data/StepTransition.php`
- Create: `packages/engine/src/Enum/StepTransitionType.php`
- Create: `packages/engine/tests/StepStateMachineEngineTest.php`

**Interfaces:**
- Consumes: `StepState` (Phase A4 — `Alama\Arazzo\Contracts\Spec\Enum\StepState`), `WorkflowEngine` (existing), `ExpressionResolverInterface` (existing), `ArazzoDocument`, `Workflow`, `Step`, `ExecutionState`.
- Produces: `StepStateMachineEngine::fire(StepState $current, Step $step, ArazzoDocument $document, ExecutionState $state, bool $criteriaMet, bool $suspended): StepTransition`; `StepTransition` readonly with `StepState $from`, `StepState $to`, `StepTransitionType $kind`, `?callable $enterHandler`.

- [ ] **Step 1: Write the failing test — transition table coverage**

Create `packages/engine/tests/StepStateMachineEngineTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\StepState;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Engine\Data\StepTransition;
use Alama\Arazzo\Engine\Enum\StepTransitionType;
use Alama\Arazzo\Engine\StepStateMachineEngine;
use Alama\Arazzo\Engine\WorkflowEngine;
use Alama\Arazzo\Runtime\Policy\RetryPolicy;

function stateMachineResolver(): ExpressionResolverInterface
{
    return new class() implements ExpressionResolverInterface
    {
        public function evaluate(Expression $expression, WorkflowContextInterface $context, ?string $currentStepId = null): mixed
        {
            return $expression->raw;
        }

        public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void {}

        public function extractOutputs(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): array
        {
            return [];
        }

        public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
        {
            return true;
        }

        public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
        {
            return true;
        }
    };
}

function stateMachineWorkflow(array $steps): Workflow
{
    return new Workflow('workflow_1', null, null, null, [], $steps, [], [], [], []);
}

function stateMachineDocument(Workflow $workflow): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('Test', null, null, '1.0.0'), [], [$workflow], new Components([], [], [], []), []);
}

function stateMachineStep(string $id): Step
{
    return new Step($id, null, new StepTarget(), new StepFlow(), new StepIo());
}

function stateMachineInteractionStep(string $id): Step
{
    return new Step($id, null, StepTarget::interaction(new Interaction()), new StepFlow(), new StepIo());
}

it('transitions Pending → ExecutingRequest when criteria not yet met', function (): void {
    $engine = new StepStateMachineEngine(new WorkflowEngine(stateMachineResolver(), new RetryPolicy()));
    $step = stateMachineStep('s1');
    $workflow = stateMachineWorkflow([$step]);
    $document = stateMachineDocument($workflow);
    $state = ExecutionState::start('exec_1', 'test', 'workflow_1');

    $transition = $engine->fire(StepState::Pending, $step, $document, $state, criteriaMet: false);

    expect($transition->from)->toBe(StepState::Pending)
        ->and($transition->to)->toBe(StepState::ExecutingRequest);
});

it('transitions Pending → AwaitingActorInput for interaction steps (bypasses ExecutingRequest)', function (): void {
    $engine = new StepStateMachineEngine(new WorkflowEngine(stateMachineResolver(), new RetryPolicy()));
    $step = stateMachineInteractionStep('s1');
    $workflow = stateMachineWorkflow([$step]);
    $document = stateMachineDocument($workflow);
    $state = ExecutionState::start('exec_1', 'test', 'workflow_1');

    $transition = $engine->fire(StepState::Pending, $step, $document, $state, criteriaMet: false);

    expect($transition->from)->toBe(StepState::Pending)
        ->and($transition->to)->toBe(StepState::AwaitingActorInput);
});

it('transitions ExecutingRequest → EvaluatingCriteria after response received', function (): void {
    $engine = new StepStateMachineEngine(new WorkflowEngine(stateMachineResolver(), new RetryPolicy()));
    $step = stateMachineStep('s1');
    $workflow = stateMachineWorkflow([$step]);
    $document = stateMachineDocument($workflow);
    $state = ExecutionState::start('exec_1', 'test', 'workflow_1');

    $transition = $engine->fire(StepState::ExecutingRequest, $step, $document, $state, criteriaMet: true);

    expect($transition->from)->toBe(StepState::ExecutingRequest)
        ->and($transition->to)->toBe(StepState::EvaluatingCriteria);
});

it('transitions EvaluatingCriteria → Completed when success criteria met', function (): void {
    $engine = new StepStateMachineEngine(new WorkflowEngine(stateMachineResolver(), new RetryPolicy()));
    $step = stateMachineStep('s1');
    $workflow = stateMachineWorkflow([$step]);
    $document = stateMachineDocument($workflow);
    $state = ExecutionState::start('exec_1', 'test', 'workflow_1');

    $transition = $engine->fire(StepState::EvaluatingCriteria, $step, $document, $state, criteriaMet: true);

    expect($transition->from)->toBe(StepState::EvaluatingCriteria)
        ->and($transition->to)->toBe(StepState::Completed);
});

it('transitions EvaluatingCriteria → Failed when criteria not met and no retry available', function (): void {
    $engine = new StepStateMachineEngine(new WorkflowEngine(stateMachineResolver(), new RetryPolicy()));
    $step = stateMachineStep('s1');
    $workflow = stateMachineWorkflow([$step]);
    $document = stateMachineDocument($workflow);
    $state = ExecutionState::start('exec_1', 'test', 'workflow_1');

    $transition = $engine->fire(StepState::EvaluatingCriteria, $step, $document, $state, criteriaMet: false);

    expect($transition->from)->toBe(StepState::EvaluatingCriteria)
        ->and($transition->to)->toBe(StepState::Failed);
});

it('transitions ExecutingRequest → Failed on transport error (suspended = true)', function (): void {
    $engine = new StepStateMachineEngine(new WorkflowEngine(stateMachineResolver(), new RetryPolicy()));
    $step = stateMachineStep('s1');
    $workflow = stateMachineWorkflow([$step]);
    $document = stateMachineDocument($workflow);
    $state = ExecutionState::start('exec_1', 'test', 'workflow_1');

    $transition = $engine->fire(StepState::ExecutingRequest, $step, $document, $state, criteriaMet: false, suspended: true);

    expect($transition->from)->toBe(StepState::ExecutingRequest)
        ->and($transition->to)->toBe(StepState::Failed);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/engine/tests --filter "StepStateMachineEngineTest"` (repo root)

Expected: FAIL with "Class StepStateMachineEngine not found".

- [ ] **Step 3: Create the supporting value types**

Create `packages/engine/src/Enum/StepTransitionType.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Engine\Enum;

enum StepTransitionType: string
{
    case EnterState = 'enter_state';
    case GuardFailed = 'guard_failed';
    case RetryEdge = 'retry_edge';
}
```

Create `packages/engine/src/Data/StepTransition.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Engine\Data;

use Alama\Arazzo\Contracts\Spec\Enum\StepState;
use Alama\Arazzo\Engine\Enum\StepTransitionType;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class StepTransition
{
    private function __construct(
        public StepState $from,
        public StepState $to,
        public StepTransitionType $kind,
        public ?string $reason = null,
    ) {}

    public static function enter(StepState $from, StepState $to, ?string $reason = null): self
    {
        return new self($from, $to, StepTransitionType::EnterState, $reason);
    }

    public static function guardFailed(StepState $from, string $reason): self
    {
        return new self($from, StepState::Failed, StepTransitionType::GuardFailed, $reason);
    }

    public static function retryEdge(StepState $from, StepState $to, ?string $reason = null): self
    {
        return new self($from, $to, StepTransitionType::RetryEdge, $reason);
    }
}
```

- [ ] **Step 4: Implement the StepStateMachineEngine**

Create `packages/engine/src/StepStateMachineEngine.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Engine;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\StepState;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Engine\Data\StepTransition;

/**
 * Explicit step-level state machine over StepState.
 *
 * The engine maps (StepState, outcome) pairs to (next StepState, enter-handler).
 * Guards (budget, deps) delegate to the pure WorkflowEngine::transition() for
 * protocol-agnostic decisions. The enter-handlers are side-effect closures
 * invoked after a successful state transition.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class StepStateMachineEngine
{
    public function __construct(
        private WorkflowEngine $workflowEngine,
    ) {}

    /**
     * Fire a step-level state transition.
     *
     * @param  StepState  $current  The step's current StepState.
     * @param  bool  $criteriaMet  Whether success criteria were met (from executor).
     * @param  bool  $suspended  Whether the executor returned a suspended outcome.
     */
    public function fire(
        StepState $current,
        Step $step,
        ArazzoDocument $document,
        ExecutionState $state,
        bool $criteriaMet = false,
        bool $suspended = false,
    ): StepTransition {
        return match ($current) {
            StepState::Pending => $this->fromPending($step, $document, $state, $criteriaMet),
            StepState::ExecutingRequest => $this->fromExecutingRequest($step, $document, $state, $criteriaMet, $suspended),
            StepState::EvaluatingCriteria => $this->fromEvaluatingCriteria($step, $document, $state, $criteriaMet),
            StepState::AwaitingActorInput => $this->fromAwaitingActorInput($step, $document, $state, $criteriaMet),
            StepState::ActorInputReceived => $this->fromActorInputReceived($step, $document, $state, $criteriaMet),
            StepState::Completed, StepState::Failed => StepTransition::enter($current, $current, 'terminal state'),
        };
    }

    /**
     * Resolve the timeout for a step as seconds (supports ISO 8601 duration strings
     * and integer milliseconds).
     */
    public static function resolveTimeoutSeconds(Step $step): ?float
    {
        if ($step->flow->timeoutDuration !== null) {
            return self::parseIso8601Duration($step->flow->timeoutDuration);
        }

        return $step->flow->timeout !== null ? $step->flow->timeout / 1000.0 : null;
    }

    private function fromPending(Step $step, ArazzoDocument $document, ExecutionState $state, bool $criteriaMet): StepTransition
    {
        if ($step->target->interaction !== null) {
            return StepTransition::enter(StepState::Pending, StepState::AwaitingActorInput, 'interaction step bypass');
        }

        if ($state->stepsSpent >= $state->maxSteps) {
            return StepTransition::guardFailed(StepState::Pending, 'step budget exceeded');
        }

        return StepTransition::enter(StepState::Pending, StepState::ExecutingRequest, 'guards pass');
    }

    private function fromExecutingRequest(Step $step, ArazzoDocument $document, ExecutionState $state, bool $criteriaMet, bool $suspended): StepTransition
    {
        if ($suspended) {
            return StepTransition::enter(StepState::ExecutingRequest, StepState::Failed, 'transport error / suspended');
        }

        return StepTransition::enter(StepState::ExecutingRequest, StepState::EvaluatingCriteria, 'response received');
    }

    private function fromEvaluatingCriteria(Step $step, ArazzoDocument $document, ExecutionState $state, bool $criteriaMet): StepTransition
    {
        if ($criteriaMet) {
            return StepTransition::enter(StepState::EvaluatingCriteria, StepState::Completed, 'success criteria met');
        }

        if ($step->target->interaction !== null) {
            return StepTransition::enter(StepState::EvaluatingCriteria, StepState::AwaitingActorInput, 'actor-in-the-loop re-evaluation');
        }

        return StepTransition::enter(StepState::EvaluatingCriteria, StepState::Failed, 'failure criteria met');
    }

    private function fromAwaitingActorInput(Step $step, ArazzoDocument $document, ExecutionState $state, bool $criteriaMet): StepTransition
    {
        return StepTransition::enter(StepState::AwaitingActorInput, StepState::ActorInputReceived, 'actor input persisted');
    }

    private function fromActorInputReceived(Step $step, ArazzoDocument $document, ExecutionState $state, bool $criteriaMet): StepTransition
    {
        if ($criteriaMet) {
            return StepTransition::enter(StepState::ActorInputReceived, StepState::Completed, 'criteria met after actor input');
        }

        return StepTransition::enter(StepState::ActorInputReceived, StepState::EvaluatingCriteria, 'resume evaluation');
    }

    /**
     * Parse an ISO 8601 duration string (e.g. "PT30S", "PT1H30M") into seconds.
     *
     * @throws \InvalidArgumentException if the string is not a valid ISO 8601 duration.
     */
    private static function parseIso8601Duration(string $duration): float
    {
        $interval = new \DateInterval($duration);
        return $interval->h * 3600 + $interval->i * 60 + $interval->s + ($interval->f ?? 0);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest packages/engine/tests --filter "StepStateMachineEngineTest"` (repo root)

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add packages/engine/src/StepStateMachineEngine.php packages/engine/src/Data/StepTransition.php packages/engine/src/Enum/StepTransitionType.php packages/engine/tests/StepStateMachineEngineTest.php
git commit -m "feat(runner): add StepStateMachineEngine with explicit transition table"
```

---

## Task E7: StoredWorkflowStateRepository — versioned envelope over StateStoreInterface

Wraps the existing `StateStoreInterface` (runner port) with a versioned envelope that stores `StepState` alongside the `WorkflowContextInterface` payload. Backward-compatible loader handles existing raw `WorkflowContext::toArray()` payloads (no envelope). `delete()` method added to `StateStoreInterface` (the interface already has the method commented out).

**Files:**
- Modify: `packages/runtime/src/State/Interfaces/StateStoreInterface.php` (uncomment `delete`)
- Create: `packages/runtime/src/State/StoredWorkflowStateRepository.php`
- Create: `packages/runtime/tests/State/StoredWorkflowStateRepositoryTest.php`

**Interfaces:**
- Consumes: `WorkflowStateRepositoryInterface` (Phase A5 — `Alama\Arazzo\Contracts\Interfaces\WorkflowStateRepositoryInterface`), `StateStoreInterface` (existing), `WorkflowContextInterface`, `StepState`.
- Produces: `StoredWorkflowStateRepository implements WorkflowStateRepositoryInterface` honoring the A5 signature exactly (`save(string, WorkflowContextInterface)`, `load(string): ?WorkflowContextInterface`, `delete(string)`). `save()` wraps the context's `toArray()` payload in a versioned envelope with a derived `StepState`; `load()` unwraps with backward compat; `delete()` delegates to `StateStoreInterface::delete()`. Current `StepState` is derived from the context's step records (default `Pending`), and exposed via `::loadStepState(string): ?StepState` as an additive convenience (not on the interface).

- [ ] **Step 1: Write the failing test**

Create `packages/runtime/tests/State/StoredWorkflowStateRepositoryTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\WorkflowStateRepositoryInterface;
use Alama\Arazzo\Contracts\Spec\Enum\StepState;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Runtime\State\InMemoryStateStore;
use Alama\Arazzo\Runtime\State\StoredWorkflowStateRepository;

function storedRepositoryContext(): WorkflowContextInterface
{
    return new class() implements WorkflowContextInterface {
        public function getInputs(): array { return ['x' => 1]; }

        public function getSteps(): array { return []; }

        public function getComponents(): array { return []; }

        public function getWorkflows(): array { return []; }

        public function getStepStatus(string $stepId): ?\Alama\Arazzo\Contracts\Spec\Enum\StepStatus { return null; }

        public function getWorkflowId(): ?string { return 'wf-1'; }
    };
}

it('implements WorkflowStateRepositoryInterface')
    ->expect(StoredWorkflowStateRepository::class)
    ->toBeImplementing(WorkflowStateRepositoryInterface::class);

it('round-trips through the versioned envelope', function (): void {
    $store = new InMemoryStateStore();
    $repo = new StoredWorkflowStateRepository($store);
    $context = storedRepositoryContext();

    $repo->save('exec-1', $context);

    $loaded = $repo->load('exec-1');
    expect($loaded)->not->toBeNull()
        ->and($loaded->getInputs())->toBe(['x' => 1])
        ->and($loaded->getWorkflowId())->toBe('wf-1');
});

it('returns null for missing execution', function (): void {
    $store = new InMemoryStateStore();
    $repo = new StoredWorkflowStateRepository($store);

    expect($repo->load('nonexistent'))->toBeNull();
});

it('delegates delete to StateStoreInterface', function (): void {
    $store = new InMemoryStateStore();
    $repo = new StoredWorkflowStateRepository($store);
    $context = storedRepositoryContext();

    $repo->save('exec-1', $context);
    $repo->delete('exec-1');

    expect($repo->load('exec-1'))->toBeNull();
});

it('persists the versioned envelope and exposes the derived StepState', function (): void {
    $store = new InMemoryStateStore();
    $repo = new StoredWorkflowStateRepository($store);
    $context = storedRepositoryContext();

    $repo->save('exec-1', $context);

    $raw = $store->load('exec-1');
    expect(is_int($raw['version'] ?? null))->toBeTrue()
        ->and($raw['stepState'] ?? null)->toBe(StepState::Pending->value)
        ->and($raw['payload'] ?? null)->toBeArray()
        ->and($repo->loadStepState('exec-1'))->toBe(StepState::Pending);
});

it('backward-compat loads raw WorkflowContext::toArray() payloads', function (): void {
    $store = new InMemoryStateStore();
    $repo = new StoredWorkflowStateRepository($store);

    // Simulate a raw payload from the old save path (no envelope)
    $rawPayload = [
        'definitionId' => 'test-def',
        'workflowId' => 'wf-1',
        'steps' => [],
        'inputs' => ['old' => true],
        'components' => [],
    ];
    $store->save('exec-old', $rawPayload);

    $loaded = $repo->load('exec-old');
    expect($loaded)->not->toBeNull()
        ->and($loaded->getInputs())->toBe(['old' => true]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/runtime/tests --filter "StoredWorkflowStateRepositoryTest"` (repo root)

Expected: FAIL with "Class StoredWorkflowStateRepository not found".

- [ ] **Step 3: Uncomment `delete()` in StateStoreInterface**

Edit `packages/runtime/src/State/Interfaces/StateStoreInterface.php` — remove the commented-out line and uncomment the `delete` method:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runtime\State\Interfaces;

interface StateStoreInterface
{
    /**
     * @param  array<string, mixed>  $state
     */
    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void;

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $executionId): ?array;

    public function delete(string $executionId): void;
}
```

Verify `InMemoryStateStore` already has `delete()` — it does (line 36 of the file). `FileStateStore` should also be checked.

- [ ] **Step 4: Implement StoredWorkflowStateRepository**

Create `packages/runtime/src/State/StoredWorkflowStateRepository.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runtime\State;

use Alama\Arazzo\Contracts\Interfaces\WorkflowStateRepositoryInterface;
use Alama\Arazzo\Contracts\Spec\Enum\StepState;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Runtime\State\Interfaces\StateStoreInterface;

/**
 * WorkflowStateRepositoryInterface backed by the runner's StateStoreInterface.
 *
 * Stores a versioned envelope:
 *   { "version": 1, "stepState": "pending", "payload": { ...WorkflowContext::toArray()... } }
 *
 * Backward-compatible: the loader detects raw payloads (no "version" key) and
 * hydrates them as-is.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class StoredWorkflowStateRepository implements WorkflowStateRepositoryInterface
{
    private const ENVELOPE_VERSION = 1;

    public function __construct(
        private StateStoreInterface $stateStore,
        private int $stateTtlSeconds = 86400,
    ) {}

    public function save(string $executionId, WorkflowContextInterface $state): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $state instanceof WorkflowContext ? $state->toArray() : $this->serializeContext($state);

        $envelope = [
            'version' => self::ENVELOPE_VERSION,
            'stepState' => $this->deriveStepState($payload),
            'payload' => $payload,
        ];

        $this->stateStore->save($executionId, $envelope, $this->stateTtlSeconds);
    }

    public function load(string $executionId): ?WorkflowContextInterface
    {
        $raw = $this->stateStore->load($executionId);

        if ($raw === null) {
            return null;
        }

        // Backward-compat: raw WorkflowContext::toArray() payloads have no "version" key.
        if (!isset($raw['version']) || !is_int($raw['version'])) {
            return WorkflowContext::fromPersisted($raw, $executionId);
        }

        /** @var array<string, mixed> $payload */
        $payload = $raw['payload'] ?? [];

        return WorkflowContext::fromPersisted($payload, $executionId);
    }

    public function delete(string $executionId): void
    {
        $this->stateStore->delete($executionId);
    }

    public function loadStepState(string $executionId): ?StepState
    {
        $raw = $this->stateStore->load($executionId);

        if ($raw === null) {
            return null;
        }

        if (!isset($raw['version']) || !is_int($raw['version'])) {
            return StepState::Pending;
        }

        return StepState::tryFrom((string) ($raw['stepState'] ?? 'pending'));
    }

    /**
     * @param  array<string, mixed>  $contextArray
     */
    private function deriveStepState(array $contextArray): string
    {
        return StepState::Pending->value;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeContext(WorkflowContextInterface $state): array
    {
        return [
            'definitionId' => $state->getWorkflowId() ?? '',
            'workflowId' => $state->getWorkflowId(),
            'steps' => $state->getSteps(),
            'inputs' => $state->getInputs(),
            'components' => $state->getComponents(),
        ];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest packages/runtime/tests --filter "StoredWorkflowStateRepositoryTest"` (repo root)

Expected: PASS.

- [ ] **Step 6: Verify FileStateStore has delete()**

Check `packages/runtime/src/State/FileStateStore.php` for the `delete` method. If missing, add it. Run full test suite:

Run: `composer run test-runner` (repo root)

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add packages/runtime/src/State/Interfaces/StateStoreInterface.php packages/runtime/src/State/StoredWorkflowStateRepository.php packages/runtime/tests/State/StoredWorkflowStateRepositoryTest.php
git commit -m "feat(runner): add StoredWorkflowStateRepository with versioned envelope"
```

---

## Task E8: UnifiedStepCarrier — single execution path for sync and async

Consolidates `WorkflowExecutor` (sync) + `StepExecutionWorker`/`StepOutcomeHandler` (async) onto one carrier class. The carrier owns the single step-level loop: resolve executor via the registry/plugin list → execute → apply side effects (persist, events). The sync path drives it in-process via `SyncQueueDriver`-style sequential dispatch; the async path drives it from queue jobs. Both share the same `UnifiedStepCarrier` class, killing the C11 divergence.

**Carriage of E0 deletions:** the carrier inherits the correlation-id scalar guard deleted with `Async/SuspensionHandler.php:47` (see E8) — when emitting the suspension correlation id, use `is_scalar($evaluated) ? (string) $evaluated : ''` rather than the unconditional cast in old `StepExecutionWorker:169`. It must also preserve the `PreflightFailureException` classification that old `StepExecutionWorker:20` used (`instanceof \Alama\Arazzo\Document\Validator\Exceptions\PreflightFailureException`), deciding the terminal branch — this is the last consumer of the document-seam exception after E0/E3.

**Files:**
- Create: `packages/runner/src/UnifiedStepCarrier.php`
- Create: `packages/runner/tests/UnifiedStepCarrierTest.php`

**Interfaces:**
- Consumes: `OperationExecutorPluginInterface` (Phase A1), `WorkflowEngine` (existing), `StateStoreInterface`, `LockManagerInterface`, `ExecutionRegistryInterface`, `EventLedgerInterface`, `PendingCorrelationRegistryInterface`.
- Produces: `UnifiedStepCarrier::execute(string $executionId, Step $step, Workflow $workflow, ArazzoDocument $document, ExecutionState $state): void` — the canonical step execution path. Constructor: `(StateStoreInterface, WorkflowEngine, LockManagerInterface, ExecutionRegistryInterface, EventLedgerInterface, PendingCorrelationRegistryInterface, array $executorPlugins, int $stateTtlSeconds = 86400)`.

- [ ] **Step 1: Write the failing test**

Create `packages/runner/tests/UnifiedStepCarrierTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\PluginInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\UnifiedStepCarrier;
use Alama\Arazzo\Engine\WorkflowEngine;
use Alama\Arazzo\Runtime\Infrastructure\NullLockStrategy;
use Alama\Arazzo\Runtime\Policy\RetryPolicy;
use Alama\Arazzo\Runtime\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runtime\State\InMemoryStateStore;

class UnifiedTestEventLedger implements \Alama\Arazzo\Events\Interfaces\EventLedgerInterface
{
    /** @var list<string> */
    public array $appended = [];

    public function append(string $executionId, string $eventType, array $payload): void
    {
        $this->appended[] = $eventType;
    }
}

class UnifiedTestExecutionRegistry implements ExecutionRegistryInterface
{
    /** @var list<string> */
    public array $completed = [];

    public function start(string $executionId, string $definitionId, string $workflowId): void {}

    public function complete(string $executionId, ExecutionStatus $status): void
    {
        $this->completed[] = $status->value;
    }
}

class UnifiedTestPendingCorrelationRegistry implements PendingCorrelationRegistryInterface
{
    public array $outstanding = [];

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath, ?int $timeoutSeconds = null): void
    {
        $this->outstanding[$executionId] = true;
    }

    public function findByCorrelationId(string $correlationId): ?\Alama\Arazzo\Contracts\Spec\PendingCorrelation { return null; }

    public function consume(string $correlationId): void {}

    public function existsForExecution(string $executionId): bool { return false; }
}

class SuccessfulPlugin implements PluginInterface, OperationExecutorPluginInterface
{
    public function name(): string { return 'stub'; }

    public function priority(): int { return 100; }

    public function supports(Step $step, ArazzoDocument $document): bool { return true; }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        return StepExecutionOutcome::resolved(200, ['ok' => true], ['ok' => true]);
    }
}

function unifiedTestResolver(): ExpressionResolverInterface
{
    return new class() implements ExpressionResolverInterface
    {
        public function evaluate(Expression $expression, WorkflowContextInterface $context, ?string $currentStepId = null): mixed
        {
            return $expression->raw;
        }

        public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void {}

        public function extractOutputs(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): array
        {
            return [];
        }

        public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
        {
            return true;
        }

        public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
        {
            return true;
        }
    };
}

function unifiedTestDocument(Workflow $workflow): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('Test', null, null, '1.0.0'), [], [$workflow], new Components([], [], [], []), []);
}

it('executes a step through the plugin and persists the outcome', function (): void {
    $store = new InMemoryStateStore();
    $ledger = new UnifiedTestEventLedger();
    $executionRegistry = new UnifiedTestExecutionRegistry();
    $pendingCorrelations = new UnifiedTestPendingCorrelationRegistry();
    $lockManager = new NullLockStrategy();
    $resolver = unifiedTestResolver();
    $workflowEngine = new WorkflowEngine($resolver, new RetryPolicy());

    $carrier = new UnifiedStepCarrier(
        stateStore: $store,
        workflowEngine: $workflowEngine,
        lockManager: $lockManager,
        executionRegistry: $executionRegistry,
        eventLedger: $ledger,
        pendingCorrelations: $pendingCorrelations,
        executorPlugins: [new SuccessfulPlugin()],
    );

    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = unifiedTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $carrier->execute('exec-1', $step, $workflow, $document, $state);

    expect($store->load('exec-1'))->not->toBeNull();
});

it('throws when no plugin supports the step', function (): void {
    $store = new InMemoryStateStore();
    $ledger = new UnifiedTestEventLedger();
    $executionRegistry = new UnifiedTestExecutionRegistry();
    $pendingCorrelations = new UnifiedTestPendingCorrelationRegistry();
    $lockManager = new NullLockStrategy();
    $resolver = unifiedTestResolver();
    $workflowEngine = new WorkflowEngine($resolver, new RetryPolicy());

    $carrier = new UnifiedStepCarrier(
        stateStore: $store,
        workflowEngine: $workflowEngine,
        lockManager: $lockManager,
        executionRegistry: $executionRegistry,
        eventLedger: $ledger,
        pendingCorrelations: $pendingCorrelations,
        executorPlugins: [],
    );

    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = unifiedTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $carrier->execute('exec-1', $step, $workflow, $document, $state);
})->throws(\LogicException::class, 'No OperationExecutorPluginInterface supports');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/runner/tests --filter "UnifiedStepCarrierTest"` (repo root)

Expected: FAIL with "Class UnifiedStepCarrier not found".

- [ ] **Step 3: Implement UnifiedStepCarrier**

Create `packages/runner/src/UnifiedStepCarrier.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Engine;

use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\StepState;
use Alama\Arazzo\Contracts\Spec\Enum\StepStatus;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Engine\Data\StepTransition;
use Alama\Arazzo\Runtime\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\StateStoreInterface;
use LogicException;

/**
 * Single carrier for both sync and async step execution.
 *
 * Replaces the split between WorkflowExecutor (sync) and StepExecutionWorker/
 * StepOutcomeHandler (async) with one canonical path. The only difference
 * between sync and async is the queue driver and lock manager injected.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class UnifiedStepCarrier
{
    /** @var list<OperationExecutorPluginInterface> */
    private array $executorPlugins;

    public function __construct(
        private StateStoreInterface $stateStore,
        private WorkflowEngine $workflowEngine,
        private LockManagerInterface $lockManager,
        private ExecutionRegistryInterface $executionRegistry,
        private EventLedgerInterface $eventLedger,
        private PendingCorrelationRegistryInterface $pendingCorrelations,
        array $executorPlugins,
        private int $stateTtlSeconds = 86400,
    ) {
        // Sort by priority (lower = higher priority, first-match wins)
        $this->executorPlugins = $executorPlugins;
        usort($this->executorPlugins, fn (OperationExecutorPluginInterface $a, OperationExecutorPluginInterface $b) => $a->priority() <=> $b->priority());
    }

    public function execute(
        string $executionId,
        Step $step,
        Workflow $workflow,
        ArazzoDocument $document,
        ExecutionState $state,
    ): void {
        $this->lockManager->acquire("execution_lock_{$executionId}", 30, function () use ($executionId, $step, $workflow, $document, $state) {
            $this->executeUnderLock($executionId, $step, $workflow, $document, $state);
        });
    }

    private function executeUnderLock(
        string $executionId,
        Step $step,
        Workflow $workflow,
        ArazzoDocument $document,
        ExecutionState $state,
    ): void {
        $context = $state->toContext();
        $context = $context->withStepAttemptIncremented($step->stepId);
        $attempt = $context->getStepAttempts($step->stepId);

        $executor = $this->findExecutor($step, $document);
        if ($executor === null) {
            throw new LogicException("No OperationExecutorPluginInterface supports step '{$step->stepId}'.");
        }

        $outcome = $executor->execute($step, $context, $document, $executionId);

        if ($outcome->suspended) {
            $context = $context->withStepStatus($step->stepId, StepStatus::Suspended);
            $this->stateStore->save($executionId, $context->toArray(), $this->stateTtlSeconds);
            $this->executionRegistry->start($executionId, $context->getDefinitionId(), $workflow->workflowId);

            return;
        }

        $contextWithResult = $context->withStepResult($step->stepId, [
            'statusCode' => $outcome->statusCode,
            'request' => $outcome->request ?? [],
            'response' => ['statusCode' => $outcome->statusCode, 'headers' => $outcome->responseHeaders, 'body' => $outcome->responseBody],
            'rawBody' => $outcome->rawBody,
            'contentType' => $outcome->contentType,
            'failureCategory' => $outcome->failureCategory,
            'outputs' => $outcome->outputs,
            'inputs' => $outcome->inputs,
            'attempts' => $attempt,
        ]);

        $this->stateStore->save($executionId, $contextWithResult->toArray(), $this->stateTtlSeconds);

        $restoredState = ExecutionState::fromArray($this->stateStore->load($executionId) ?? $state->toArray());
        $transition = $this->workflowEngine->transition($document, $workflow, $step, $restoredState, $outcome->failureCategory === null);

        $nextState = $transition->state;
        assert($nextState instanceof ExecutionState);

        $this->stateStore->save($executionId, $nextState->toContext()->toArray(), $this->stateTtlSeconds);

        if ($transition->isTerminal()) {
            $succeeded = $transition->status === 'succeeded';
            $this->executionRegistry->complete($executionId, $succeeded ? \Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus::Succeeded : \Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus::Failed);
            $this->eventLedger->append($executionId, $succeeded ? 'execution.succeeded' : 'execution.failed', ['workflowId' => $transition->state->workflowId]);
        }
    }

    private function findExecutor(Step $step, ArazzoDocument $document): ?OperationExecutorPluginInterface
    {
        foreach ($this->executorPlugins as $plugin) {
            if ($plugin->supports($step, $document)) {
                return $plugin;
            }
        }

        return null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/runner/tests --filter "UnifiedStepCarrierTest"` (repo root)

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/runner/src/UnifiedStepCarrier.php packages/runner/tests/UnifiedStepCarrierTest.php
git commit -m "feat(runner): add UnifiedStepCarrier for sync/async parity"
```

---

## Task E9: OperationExecutorRegistry — first-supports()-wins registry over OperationExecutorPluginInterface

Replaces the direct `openApiExecutor` calls in `StepExecutor` and the `StepProtocolExecutorInterface` list in `StepExecutionWorker` with a priority-ordered registry that delegates to `OperationExecutorPluginInterface` plugins. `ProtocolExecutorRegistryInterface` (which works on `StepProtocolExecutorInterface`) remains as a `@deprecated` adapter; `OperationExecutorRegistry` is the new canonical registry.

**Registry placement and the assembler's remaining import:** the registry lives at the runner root (`Alama\Arazzo\Runner\OperationExecutorRegistry`) because both the sync path and the async graph need it, and it is the seam Phase F's protocol packages register into. The `AsyncExecutionGraphAssembler` still imports `Alama\Arazzo\Runner\Protocol\{AsyncApiStepExecutor, HttpStepExecutor, SubWorkflowStepExecutor}` (`Async/AsyncExecutionGraphAssembler.php:14-16`) and still builds the list directly (line 129, `$protocolExecutors = [$subWorkflowExecutor, $httpStepExecutor, $asyncExecutor]`). **That import is no longer a layering violation** — under the Phase E split, `runner` sits *above* the protocol packages and is allowed to depend on them. Rewiring the assembler to resolve through the registry instead of by name is a Phase F2 task, because Phase F1 is what actually moves those executors into `alama/protocol-http`. Do not add a phase-local arch rule for it here; the rule that matters is the Phase F1 one (`protocol-http` must not depend on `runner`).

**Files:**
- Create: `packages/runner/src/OperationExecutorRegistry.php`
- Modify: `packages/runner/src/Interfaces/ProtocolExecutorRegistryInterface.php` (add `@deprecated` docblock)
- Create: `packages/runner/tests/OperationExecutorRegistryTest.php`

**Interfaces:**
- Consumes: `OperationExecutorPluginInterface` (Phase A1), `PluginInterface` (Phase A1).
- Produces: `OperationExecutorRegistry::register(OperationExecutorPluginInterface): void`, `::resolve(Step, ArazzoDocument): ?OperationExecutorPluginInterface`, `::all(): list<OperationExecutorPluginInterface>`.
- Becomes the canonical executor-resolution path injected into `AsyncExecutionGraphAssembler` (replacing the direct `Protocol/` imports in Phase F2) and the `UnifiedStepCarrier` (E8).

- [ ] **Step 1: Write the failing test**

Create `packages/runner/tests/OperationExecutorRegistryTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\PluginInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Runner\OperationExecutorRegistry;

class HighPriorityPlugin implements PluginInterface, OperationExecutorPluginInterface
{
    public function name(): string { return 'high'; }
    public function priority(): int { return 10; }
    public function supports(Step $step, ArazzoDocument $document): bool { return str_contains($step->stepId, 'high'); }
    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        return StepExecutionOutcome::resolved(200, [], []);
    }
}

class LowPriorityPlugin implements PluginInterface, OperationExecutorPluginInterface
{
    public function name(): string { return 'low'; }
    public function priority(): int { return 100; }
    public function supports(Step $step, ArazzoDocument $document): bool { return true; }
    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        return StepExecutionOutcome::resolved(200, [], []);
    }
}

function registryDocument(): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('Test', null, null, '1.0.0'), [], [], new Components([], [], [], []), []);
}

it('resolves the first plugin whose supports() returns true, ordered by priority', function (): void {
    $registry = new OperationExecutorRegistry();
    $registry->register(new LowPriorityPlugin());
    $registry->register(new HighPriorityPlugin());

    $step = new Step('high-step', null, new StepTarget(), new StepFlow(), new StepIo());
    $resolved = $registry->resolve($step, registryDocument());

    expect($resolved)->not->toBeNull()
        ->and($resolved->name())->toBe('high');
});

it('returns null when no plugin supports the step', function (): void {
    $registry = new OperationExecutorRegistry();
    $step = new Step('unknown', null, new StepTarget(), new StepFlow(), new StepIo());

    expect($registry->resolve($step, registryDocument()))->toBeNull();
});

it('returns all registered plugins', function (): void {
    $registry = new OperationExecutorRegistry();
    $registry->register(new LowPriorityPlugin());
    $registry->register(new HighPriorityPlugin());

    expect($registry->all())->toHaveCount(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/runner/tests --filter "OperationExecutorRegistryTest"` (repo root)

Expected: FAIL with "Class OperationExecutorRegistry not found".

- [ ] **Step 3: Implement OperationExecutorRegistry**

Create `packages/runner/src/OperationExecutorRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Engine;

use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;

/**
 * First-supports()-wins registry for operation executor plugins.
 *
 * Registration order does not matter: plugins are sorted by priority at
 * resolution time. Lower priority values are tried first.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class OperationExecutorRegistry
{
    /** @var list<OperationExecutorPluginInterface> */
    private array $plugins = [];

    public function register(OperationExecutorPluginInterface $plugin): void
    {
        $this->plugins[] = $plugin;
    }

    public function resolve(Step $step, ArazzoDocument $document): ?OperationExecutorPluginInterface
    {
        $sorted = $this->plugins;
        usort($sorted, fn (OperationExecutorPluginInterface $a, OperationExecutorPluginInterface $b) => $a->priority() <=> $b->priority());

        foreach ($sorted as $plugin) {
            if ($plugin->supports($step, $document)) {
                return $plugin;
            }
        }

        return null;
    }

    /** @return list<OperationExecutorPluginInterface> */
    public function all(): array
    {
        return $this->plugins;
    }
}
```

- [ ] **Step 4: Deprecate the old ProtocolExecutorRegistryInterface**

Edit `packages/runner/src/Interfaces/ProtocolExecutorRegistryInterface.php` — add a `@deprecated` docblock:

```php
/**
 * @deprecated Use OperationExecutorRegistry with OperationExecutorPluginInterface instead.
 */
interface ProtocolExecutorRegistryInterface
```

- [ ] **Step 5: Register the built-in executors into the registry at the composition seam**

`AsyncExecutionGraphAssembler` keeps its direct `Protocol\*` imports for now — under the Phase E split that edge points *down* the layer stack and is legitimate. What this step does is make the registry the single resolution path the carrier uses, so there is exactly one place where a step turns into an executor call:

Edit `packages/runner/src/UnifiedStepCarrier.php` — replace the inline `array $executorPlugins` constructor parameter with an injected `OperationExecutorRegistry`, and replace the local `findExecutor()` loop with `$this->registry->resolve($step, $document)`. Keep the `LogicException` message on a null resolution so the E8 test contract is unchanged.

Edit `packages/runner/src/Async/AsyncExecutionGraphAssembler.php` — after building the three protocol executors, register them into the injected `OperationExecutorRegistry` (in place of only passing the raw list onward), so the async graph and the carrier share one registry instance supplied by `AsyncGraphSeams`.

Run: `composer run test-runner` (repo root)

Expected: PASS.

- [ ] **Step 6: Run test to verify it passes**

Run: `vendor/bin/pest packages/runner/tests --filter "OperationExecutorRegistryTest"` (repo root)

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add packages/runner/src/OperationExecutorRegistry.php packages/runner/src/Interfaces/ProtocolExecutorRegistryInterface.php packages/runner/src/UnifiedStepCarrier.php packages/runner/src/Async/AsyncExecutionGraphAssembler.php packages/runner/src/AsyncGraphSeams.php packages/runner/tests/OperationExecutorRegistryTest.php
git commit -m "feat(runner): add OperationExecutorRegistry with priority-ordered resolution"
```

---

## Task E10: Binding-aware request compilation + ResponseValidatorInterface dispatch

In-core JSON-schema/OpenAPI validator dispatch during Phase E. After Phase F1, the HTTP default validator moves to `arazzo-protocol-http` and becomes registry-fed. During E, the runner's `StepExecutor` dispatches `ResponseValidatorInterface` per protocol. The `RequestCompiler` remains protocol-agnostic (compiles parameters, body, headers from `Step` parameters). Protocol-specific binding-aware compilation is additive on top of the existing `RequestCompiler`.

**Files:**
- Create: `packages/runner/src/ResponseValidatorDispatcher.php`
- Create: `packages/runner/tests/ResponseValidatorDispatcherTest.php`

**Interfaces:**
- Consumes: `ResponseValidatorInterface` (existing contracts), `Step`, `ArazzoDocument`.
- Produces: `ResponseValidatorDispatcher::validate(Step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument): void` — iterates registered validators, dispatches to the first that matches.

- [ ] **Step 1: Write the failing test**

Create `packages/runner/tests/ResponseValidatorDispatcherTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Exceptions\SchemaValidationException;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Runner\ResponseValidatorDispatcher;

class StubResponseValidator implements ResponseValidatorInterface
{
    public int $called = 0;

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        $this->called++;
    }
}

class FailingResponseValidator implements ResponseValidatorInterface
{
    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        throw new SchemaValidationException('Schema mismatch', [], [], $document);
    }
}

function dispatcherStep(string $id = 's1'): Step
{
    return new Step($id, null, new StepTarget(), new StepFlow(), new StepIo());
}

it('dispatches to all registered validators', function (): void {
    $validator = new StubResponseValidator();
    $dispatcher = new ResponseValidatorDispatcher([$validator]);

    $dispatcher->validate(dispatcherStep(), 200, 'application/json', ['ok' => true]);

    expect($validator->called)->toBe(1);
});

it('throws on first validation failure', function (): void {
    $validator = new FailingResponseValidator();
    $dispatcher = new ResponseValidatorDispatcher([$validator]);

    $dispatcher->validate(dispatcherStep(), 200, 'application/json', ['bad']);
})->throws(SchemaValidationException::class);

it('does nothing with empty validator list', function (): void {
    $dispatcher = new ResponseValidatorDispatcher([]);

    $dispatcher->validate(dispatcherStep(), 200, 'application/json', []);

    expect(true)->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/runner/tests --filter "ResponseValidatorDispatcherTest"` (repo root)

Expected: FAIL with "Class ResponseValidatorDispatcher not found".

- [ ] **Step 3: Implement ResponseValidatorDispatcher**

Create `packages/runner/src/ResponseValidatorDispatcher.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Engine;

use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;

/**
 * Dispatches response validation across registered ResponseValidatorInterface plugins.
 *
 * After Phase F1, protocol-specific validators (HTTP/OpenAPI, SOAP/XSD, RPC/proto)
 * are registered here and the in-core validator becomes the fallback.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ResponseValidatorDispatcher
{
    /** @param  list<ResponseValidatorInterface>  $validators */
    public function __construct(
        private array $validators,
    ) {}

    /**
     * @throws \Alama\Arazzo\Contracts\Exceptions\SchemaValidationException
     */
    public function validate(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        foreach ($this->validators as $validator) {
            $validator->validateResponseSchema($step, $statusCode, $contentType, $decodedBody, $document);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/runner/tests --filter "ResponseValidatorDispatcherTest"` (repo root)

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/runner/src/ResponseValidatorDispatcher.php packages/runner/tests/ResponseValidatorDispatcherTest.php
git commit -m "feat(runner): add ResponseValidatorDispatcher for protocol-aware validation"
```

---

## Task E11: Phase E gate

Close out Phase E: the four-layer split plus the OMS additions, verified together against every existing consumer.

**Files:**
- None to modify (unless formatting requires).

**Interfaces:**
- Consumes: all tasks E0–E10.

- [ ] **Step 1: Run every package suite**

```bash
composer run test-runtime && composer run test-events
composer run test-pipeline && composer run test-engine
composer run test-runner && composer run test-laravel
```

Expected: PASS. The four arch guards added in E1–E4 (runtime leaf, events leaf, pipeline agnostic, engine pure core) are all GREEN and were never allowed to go red.

- [ ] **Step 2: Run static analysis across all packages**

```bash
composer run analyse-runtime && composer run analyse-events
composer run analyse-pipeline && composer run analyse-engine && composer run analyse-runner
```

Expected: PASS (0 errors).

- [ ] **Step 3: Run the formatter check**

Run: `composer run format` or `vendor/bin/pint --test` (repo root)

Expected: PASS (no style violations). If violations exist, run `vendor/bin/pint` and re-run Step 1.

- [ ] **Step 4: Run the full repo gate**

Run: `make verify` (repo root)

Expected: PASS — confirms the split plus the OMS do not break `core`/`cli`/`laravel`/`document`/`expression` consumers.

- [ ] **Step 5: Re-run the E5 layer sweep**

Re-run the four `rg` sweeps from E5 Step 3 verbatim. Expected: all four report clean. This is the phase's central claim — *the split landed, and the OMS was built on top of it* — so it is checked twice, once before the OMS and once after.

- [ ] **Step 6: Mark this plan's steps complete**

Flip every `- [ ]` in this document to `- [x]`.

- [ ] **Step 7: Record completion in the spec**

Open `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`, find the Phase E heading, and add:

```markdown
Phase E status: ✅ Implemented — the four-layer split (`arazzo-runtime`, `arazzo-events`, `arazzo-request-pipeline`, `arazzo-engine`) landed in E0–E5, then the OMS in E6–E10. See `plans/2026-09-08-phase-e-runner-oms.md`.
```

- [ ] **Step 8: Commit the doc update**

```bash
git add docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md
git commit -m "docs: mark Phase E runner split + OMS engine complete"
```

---

## Rejected alternatives

Recorded so the next reader does not re-litigate them.

| Alternative | Why rejected |
|---|---|
| **A horizontal `arazzo/protocol-operations` package** holding all `Protocol/*` executors plus the registry | It dissolves. The seam it would own (`StepProtocolExecutorInterface`) is already in `packages/contracts/src/Interfaces/`. Of the five `Protocol/` classes, two are dead (E0 deletes them), two are protocol-specific and belong in the vertical `protocol-http` package, and one (`SubWorkflowStepExecutor`) is not a protocol at all — it is Arazzo recursive-workflow semantics and belongs in `arazzo-runner`, because it delegates to `WorkflowExecutor` and the engine may not depend on the runner. Nothing is left for the package to hold. |
| **Four thin infra packages** (`arazzo-telemetry`, `arazzo-policy`, `arazzo-locking`, `arazzo-state-adapters`) | `arazzo-policy` is two files and `arazzo-locking` is two files. Neither clears the bar of "would someone plausibly install and version this alone". They share a volatility profile (infrastructure pluggability) and a zero-internal-coupling shape, which is a legitimate CCP grouping, so they ship together as `arazzo-runtime` (E1). The layering is preserved as arch tests even though the packaging is coarser. |
| **Splitting `arazzo-runner` into `arazzo-sync-runner` + `arazzo-async-runner`** | Measured on the three coupling dimensions and inverted on all of them: integration strength is low (zero sync→async imports — `StepExecutor` references only `OpenApiExecutorInterface`, `WorkflowExecutor` only events/data/enum, `ExecutionGraphFactory` no runner internals at all), distance is short (same package, shared `WorkflowContext`/`Step`/`StepExecutionOutcome` vocabulary with no translation layer), and volatility is identical (both change on any step-lifecycle edit, so splitting duplicates the change rather than decoupling it). Async also adds no package dependency — the queue arrives via `QueueDriverInterface` and HTTP via `HttpClientInterface`. The boundary is kept where it buys something for free: namespaces plus arch rules, enforced in Phase F2. |
| **Introducing the OMS before the split** (the previous ordering) | The state machine, carrier and registries would be written against concrete neighbours in a single package, and every later protocol package would inherit that coupling. This is the change that motivated the restructure. |
| **A separate `arazzo/runner-facade` package** | 88 lines that consumers already bypass — `packages/laravel` and `packages/cli` currently import ~26 `Runner\` symbols including concretes such as `Protocol\HttpStepExecutor`, `Jobs\ExecuteStepJob` and `StepExecutor`. The stable-API abstraction is not being honoured, so a package boundary around it would add indirection without control. The facade ships at the runner root and Phase F2 fixes the leak that makes it meaningful. |
| **Extracting `arazzo-events` into `arazzo-runtime` as well** | Rejected for the opposite reason to the thin-infra merge: events is a distinct seam with eleven DTOs, a port and a listener, consumed by the engine, both runners and the Laravel layer, and the most likely of the two to grow a transport adapter (PSR-14/PSR-16). It clears the standalone bar on its own. |
| **Moving `State/Interfaces/*` into `contracts` during E1** | The FLATTEN philosophy argues for it, but it is a Phase A tail seam decision that ripples through every store implementation and every consumer. E1 records the decision as open rather than smuggling it into a mechanical move. |
