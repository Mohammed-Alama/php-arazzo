# Flatten Runner: Plan & Task Tracker

> **Branch:** `feat/runner-flatten`
> **Worktree:** `.worktrees/feat/runner-flatten`
> **Goal:** Lift all internal modules out of `Runner/` into flat, single-word top-level directories under `packages/core/src/`. Consolidate all interfaces into a global `Contracts/` directory. Move `Cli/` under `Console/`.
> **Estimated `use` statement updates:** ~1,149

---

## Target Directory Structure

```
packages/core/src/
├── Async/                  ← from Runner/Async/
├── Console/
│   ├── Application.php
│   ├── DocumentLoader.php
│   ├── Command/
│   │   ├── RunCommand.php
│   │   ├── ValidateCommand.php
│   │   ├── ExplainCommand.php
│   │   ├── ListWorkflowsCommand.php
│   │   └── RenderCommand.php
│   └── Cli/                ← from Runner/Cli/
│       ├── CliRunner.php
│       ├── CliRunResult.php
│       ├── InProcessExecutionRegistry.php
│       └── NullEventLedger.php
├── Context/                ← from Runner/Context/
├── Contracts/              ← NEW: all 21 interfaces consolidated
├── Events/                 ← from Runner/Events/
├── Evaluation/             ← from Runner/Evaluation/
├── Exceptions/             ← from Runner/Exceptions/
├── Execution/              ← from Runner/Execution/
├── Expression/             (existing, unchanged)
├── Generator/              (existing, unchanged)
├── Jobs/                   ← from Runner/Jobs/
├── Normalizer/             ← from Runner/Normalizer/
├── Parser/                 (existing, unchanged)
├── Policy/                 ← from Runner/Policy/
├── Protocol/               ← from Runner/Protocol/
├── Renderer/               (existing, unchanged)
├── Resolver/               (existing, unchanged)
├── Runner/                 (ELIMINATED — all subdirs moved out)
├── Spec/                   (existing, gains StepStatus enum)
├── State/                  ← from Runner/State/
├── Support/                (existing, unchanged)
├── Telemetry/              ← from Runner/Telemetry/
└── Validator/              (existing, unchanged)
```

---

## Pre-Flight: Resolve Circular Dependencies

Three cycles must be broken before flattening. Each is a separate commit.

### Task 0a: Move StepStatus to Spec/Enum

- [ ] Move `packages/core/src/Runner/Execution/Enum/StepStatus.php` → `packages/core/src/Spec/Enum/StepStatus.php`
- [ ] Change namespace from `Alama\Arazzo\Runner\Execution\Enum` to `Alama\Arazzo\Spec\Enum`
- [ ] Update all `use` statements referencing `Runner\Execution\Enum\StepStatus` → `Spec\Enum\StepStatus`
- [ ] Run `composer test` and `composer analyse` — expect PASS
- [ ] Commit `refactor: move StepStatus enum to Spec layer`

### Task 0b: Move OutputExtractorInterface to Contracts/

- [ ] Move `packages/core/src/Runner/Execution/Contracts/OutputExtractorInterface.php` → `packages/core/src/Contracts/OutputExtractorInterface.php`
- [ ] Change namespace from `Alama\Arazzo\Runner\Execution\Contracts` to `Alama\Arazzo\Contracts`
- [ ] Update all `use` statements referencing `Runner\Execution\Contracts\OutputExtractorInterface` → `Contracts\OutputExtractorInterface`
- [ ] Run tests — expect PASS
- [ ] Commit `refactor: move OutputExtractorInterface to global Contracts`

### Task 0c: Move SchemaValidatorInterface to Contracts/

- [ ] Move `packages/core/src/Runner/Execution/Contracts/SchemaValidatorInterface.php` → `packages/core/src/Contracts/SchemaValidatorInterface.php`
- [ ] Change namespace from `Alama\Arazzo\Runner\Execution\Contracts` to `Alama\Arazzo\Contracts`
- [ ] Update all `use` statements referencing `Runner\Execution\Contracts\SchemaValidatorInterface` → `Contracts\SchemaValidatorInterface`
- [ ] Run tests — expect PASS
- [ ] Commit `refactor: move SchemaValidatorInterface to global Contracts`

---

## Phase 1: Create Global Contracts Directory

### Task 1: Consolidate all 21 interfaces into `packages/core/src/Contracts/`

- [ ] Create `packages/core/src/Contracts/` directory
- [ ] Move the following interfaces (each becomes `Alama\Arazzo\Contracts\{Interface}`):

| Interface | From Namespace | `use` Refs |
|---|---|---:|
| `AiClientInterface` | `Generator\Contracts` | 5 |
| `PendingCorrelationRegistryInterface` | `Runner\Context\Contracts` | 21 |
| `StateStoreInterface` | `Runner\Context\Contracts` | 27 |
| `ExpressionEvaluatorInterface` | `Runner\Evaluation\Contracts` | 4 |
| `CriteriaEvaluatorInterface` | `Runner\Evaluation\Contracts` | 2 |
| `ExpressionResolverInterface` | `Runner\Evaluation\Contracts` | 35 |
| `ExecutionRegistryInterface` | `Runner\Execution\Contracts` | 20 |
| `DefinitionRegistryInterface` | `Runner\Execution\Contracts` | 13 |
| `WritableDefinitionRegistryInterface` | `Runner\Execution\Contracts` | 2 |
| `QueueDriverInterface` | `Runner\Execution\Contracts` | 15 |
| `EventLedgerInterface` | `Runner\Execution\Contracts` | 28 |
| `LockManagerInterface` | `Runner\Execution\Contracts` | 16 |
| `HttpClientInterface` | `Runner\Execution\Contracts` | 10 |
| `OpenApiExecutorInterface` | `Runner\Execution\Contracts` | 11 |
| `StepProtocolExecutorInterface` | `Runner\Execution\Contracts` | 13 |
| `LockStrategyInterface` | `Runner\Contract` (singular) | 3 |
| `ProtocolExecutorRegistryInterface` | `Runner\Protocol` | 0 |
| `BackoffCalculatorInterface` | `Runner\Policy` | 1 |
| `OpenApiNormalizerInterface` | `Runner\Normalizer` | 0 |

- [ ] Update ~230 `use` statements across both `packages/core/` and `packages/laravel/`
- [ ] Remove empty `Contracts/` subdirectories from Runner subdirs
- [ ] Keep `Runner/Contract/` (singular) — it has 3 concrete lock implementations; only interface moved
- [ ] Run full test suite — expect PASS
- [ ] Commit `refactor: consolidate all interfaces into global Contracts directory`

---

## Phase 2: Flatten Modules (one commit each)

Each step: move directory, update namespace in every file, update all `use` statements, run tests.

### Task 2a: Flatten Normalizer

- [ ] Move `packages/core/src/Runner/Normalizer/` → `packages/core/src/Normalizer/`
- [ ] Namespace: `Alama\Arazzo\Runner\Normalizer` → `Alama\Arazzo\Normalizer`
- [ ] Update 48 `use` statements
- [ ] Consumers to fix: none (zero internal deps, only consumed by Console and Resolver)
- [ ] Run tests — expect PASS
- [ ] Commit `refactor: flatten Normalizer to top level`

### Task 2b: Flatten Telemetry

- [ ] Move `packages/core/src/Runner/Telemetry/` → `packages/core/src/Telemetry/`
- [ ] Namespace: `Alama\Arazzo\Runner\Telemetry` → `Alama\Arazzo\Telemetry`
- [ ] Update 4 `use` statements
- [ ] Consumers to fix: `Execution/StepExecutionWorker.php`, `Console/Cli/CliRunner.php`
- [ ] Run tests — expect PASS
- [ ] Commit `refactor: flatten Telemetry to top level`

### Task 2c: Flatten Policy

- [ ] Move `packages/core/src/Runner/Policy/` → `packages/core/src/Policy/`
- [ ] Namespace: `Alama\Arazzo\Runner\Policy` → `Alama\Arazzo\Policy`
- [ ] Update 4 `use` statements
- [ ] Consumers to fix: `Execution/WorkflowEngine.php`
- [ ] Run tests — expect PASS
- [ ] Commit `refactor: flatten Policy to top level`

### Task 2d: Flatten State

- [ ] Move `packages/core/src/Runner/State/` → `packages/core/src/State/`
- [ ] Namespace: `Alama\Arazzo\Runner\State` → `Alama\Arazzo\State`
- [ ] Update 12 `use` statements
- [ ] Consumers to fix: `Execution/WorkflowExecutor.php`, `Execution/StepExecutionWorker.php`
- [ ] Run tests — expect PASS
- [ ] Commit `refactor: flatten State to top level`

### Task 2e: Flatten Protocol

- [ ] Move `packages/core/src/Runner/Protocol/` → `packages/core/src/Protocol/`
- [ ] Namespace: `Alama\Arazzo\Runner\Protocol` → `Alama\Arazzo\Protocol`
- [ ] Update 2 `use` statements
- [ ] Consumers to fix: none (fan-out only)
- [ ] Run tests — expect PASS
- [ ] Commit `refactor: flatten Protocol to top level`

### Task 2f: Flatten Evaluation

- [ ] Move `packages/core/src/Runner/Evaluation/` → `packages/core/src/Evaluation/`
- [ ] Namespace: `Alama\Arazzo\Runner\Evaluation` → `Alama\Arazzo\Evaluation`
- [ ] Update 167 `use` statements
- [ ] Consumers to fix: `Execution/`, `Protocol/`, `Async/`, `Console/Cli/`
- [ ] Run tests — expect PASS
- [ ] Commit `refactor: flatten Evaluation to top level`

### Task 2g: Flatten Context

- [ ] Move `packages/core/src/Runner/Context/` → `packages/core/src/Context/`
- [ ] Namespace: `Alama\Arazzo\Runner\Context` → `Alama\Arazzo\Context`
- [ ] Update 160 `use` statements
- [ ] Consumers to fix: `Execution/`, `Evaluation/`, `State/`, `Policy/`, `Protocol/`, `Async/`, `Console/Cli/`
- [ ] Run tests — expect PASS
- [ ] Commit `refactor: flatten Context to top level`

### Task 2h: Flatten Execution

- [ ] Move `packages/core/src/Runner/Execution/` → `packages/core/src/Execution/`
- [ ] Namespace: `Alama\Arazzo\Runner\Execution` → `Alama\Arazzo\Execution`
- [ ] Update 384 `use` statements
- [ ] Consumers to fix: 7 internal + `Console/` (15) + `Laravel/Bindings` (71) + `Laravel/Persistence` (7) + `Laravel/Http` (4)
- [ ] Run tests — expect PASS
- [ ] Commit `refactor: flatten Execution to top level`

### Task 2i: Flatten Events

- [ ] Move `packages/core/src/Runner/Events/` → `packages/core/src/Events/`
- [ ] Namespace: `Alama\Arazzo\Runner\Events` → `Alama\Arazzo\Events`
- [ ] Update 90 `use` statements
- [ ] Run tests — expect PASS
- [ ] Commit `refactor: flatten Events to top level`

### Task 2j: Flatten Exceptions

- [ ] Move `packages/core/src/Runner/Exceptions/` → `packages/core/src/Exceptions/`
- [ ] Namespace: `Alama\Arazzo\Runner\Exceptions` → `Alama\Arazzo\Exceptions`
- [ ] Update 28 `use` statements
- [ ] Run tests — expect PASS
- [ ] Commit `refactor: flatten Exceptions to top level`

### Task 2k: Flatten Jobs

- [ ] Move `packages/core/src/Runner/Jobs/` → `packages/core/src/Jobs/`
- [ ] Namespace: `Alama\Arazzo\Runner\Jobs` → `Alama\Arazzo\Jobs`
- [ ] Update 17 `use` statements
- [ ] Run tests — expect PASS
- [ ] Commit `refactor: flatten Jobs to top level`

### Task 2l: Move Cli under Console

- [ ] Move `packages/core/src/Runner/Cli/` → `packages/core/src/Console/Cli/`
- [ ] Namespace: `Alama\Arazzo\Runner\Cli` → `Alama\Arazzo\Console\Cli`
- [ ] Update 2 `use` statements (2 test files only)
- [ ] Run tests — expect PASS
- [ ] Commit `refactor: move Cli runner under Console namespace`

### Task 2m: Flatten Async

- [ ] Move `packages/core/src/Runner/Async/` → `packages/core/src/Async/`
- [ ] Namespace: `Alama\Arazzo\Runner\Async` → `Alama\Arazzo\Async`
- [ ] Update 7 `use` statements
- [ ] Run tests — expect PASS
- [ ] Commit `refactor: flatten Async to top level`

### Task 2n: Remove empty Runner directory

- [ ] Verify `packages/core/src/Runner/` is empty (or has only leftover files)
- [ ] Remove `packages/core/src/Runner/` directory
- [ ] Remove `packages/core/tests/Runner/` test directory structure (tests move with modules)
- [ ] Commit `refactor: eliminate Runner directory`

---

## Phase 3: Update External Consumers

### Task 3a: Update Console imports

- [ ] Update `Console/Command/RunCommand.php` — 15 refs to new top-level namespaces
- [ ] Update `Console/Command/ValidateCommand.php` — check refs
- [ ] Update `Console/Command/ExplainCommand.php` — check refs
- [ ] Run `composer analyse-core` — expect PASS
- [ ] Commit `refactor: update Console imports for flattened modules`

### Task 3b: Update Laravel Bindings

- [ ] Update `packages/laravel/src/Bindings/ExecutionBindings.php` — ~50 refs
- [ ] Update `packages/laravel/src/Bindings/PersistenceBindings.php` — ~7 refs
- [ ] Update `packages/laravel/src/Bindings/GeneratorBindings.php` — check refs
- [ ] Update `packages/laravel/src/Bindings/HttpBindings.php` — check refs
- [ ] Update `packages/laravel/src/Bindings/EventBindings.php` — check refs
- [ ] Run `composer analyse-laravel` — expect PASS
- [ ] Commit `refactor: update Laravel bindings for flattened modules`

### Task 3c: Update Laravel adapters

- [ ] Update `packages/laravel/src/Persistence/*` — refs to Contracts, Execution, Spec
- [ ] Update `packages/laravel/src/Http/*` — refs to Execution, Spec
- [ ] Update `packages/laravel/src/Queue/*` — refs to Contracts, Jobs
- [ ] Update `packages/laravel/src/Lock/*` — refs to Contracts
- [ ] Update `packages/laravel/src/State/*` — refs to Contracts
- [ ] Run `composer test-laravel` — expect PASS
- [ ] Commit `refactor: update Laravel adapter imports for flattened modules`

---

## Phase 4: Update Layering Rules

### Task 4: Update LayeringDoc.php and regenerate docs

- [ ] Edit `scripts/generate-docs/LayeringDoc.php` → `LAYER_ORDER`:

```php
const LAYER_ORDER = [
    'Expression',   // Layer 0
    'Spec',         // Layer 1
    'Support',      // Layer 2
    'Contracts',    // Layer 3 (NEW — all interfaces)
    'Generator',    // Layer 4
    'Parser',       // Layer 5
    'Resolver',     // Layer 6
    'Normalizer',   // Layer 7 (NEW)
    'Validator',    // Layer 8
    'Telemetry',    // Layer 9 (NEW)
    'Policy',       // Layer 10 (NEW)
    'State',        // Layer 11 (NEW)
    'Evaluation',   // Layer 12 (NEW)
    'Context',      // Layer 13 (NEW)
    'Protocol',     // Layer 14 (NEW)
    'Execution',    // Layer 15 (NEW)
    'Events',       // Layer 16 (NEW)
    'Exceptions',   // Layer 17 (NEW)
    'Jobs',         // Layer 18 (NEW)
    'Async',        // Layer 19 (NEW)
    'Console',      // Layer 20
    'Renderer',     // Layer 21
];
```

- [ ] Run `php scripts/generate-docs.php` — verify zero layering violations
- [ ] Verify `docs/generated/layering.md` shows no red edges
- [ ] Commit `refactor: update layering rules for flattened module structure`

---

## Phase 5: Cleanup & Verification

### Task 5a: Cleanup composer.json

- [ ] Remove orphaned `autoload-dev` entries from `packages/core/composer.json`:
  - `Alama\\LaravelArazzo\\Execution\\` → `../../src/Execution/`
  - `Alama\\LaravelArazzo\\Events\\` → `../../src/Events/`
- [ ] Verify PSR-4 autoload still resolves correctly
- [ ] Commit `chore: clean up orphaned autoload-dev entries`

### Task 5b: Regenerate all docs

- [ ] Run `php scripts/generate-docs.php`
- [ ] Verify these files updated correctly:
  - `docs/generated/public-api.md`
  - `docs/generated/namespace-graph.md`
  - `docs/generated/coupling-metrics.md`
  - `docs/generated/layering.md`
  - `docs/generated/dependency-flow.md`
  - `docs/generated/aggregate-map.md`
  - `docs/generated/coverage-risk.md`
  - `docs/generated/solid-metrics.md`
  - `docs/generated/modularization-progress.md`
- [ ] Commit `docs: regenerate architecture docs for flattened structure`

### Task 5c: Full gates

- [ ] Run `make verify` — expect PASS (pint + phpstan + pest)
- [ ] Run `make quality-gates` — record metrics
- [ ] Verify no BC breaks in public API surface
- [ ] Commit `chore: post-flatten verification`

---

## Dependency Graph

```
Pre-flight (0a, 0b, 0c)     ──► Phase 1 (Contracts)
                                      │
Phase 2a (Normalizer) ────────────────┤
Phase 2b (Telemetry)  ────────────────┤
Phase 2c (Policy)     ────────────────┤  (can be parallelized)
Phase 2d (State)      ────────────────┤
Phase 2e (Protocol)   ────────────────┤
                                      │
Phase 2f (Evaluation) ────────────────┤  (depends on Contracts)
Phase 2g (Context)    ────────────────┤  (depends on Contracts)
Phase 2h (Execution)  ────────────────┘  (depends on Contracts, Context, Evaluation)
         │
Phase 2i (Events)     ──┐
Phase 2j (Exceptions) ──┤  (after Execution moves)
Phase 2k (Jobs)       ──┤
Phase 2l (Cli→Console)──┤
Phase 2m (Async)      ──┘
         │
Phase 2n (Remove Runner/)
         │
Phase 3 (Update external consumers)
         │
Phase 4 (Layering rules)
         │
Phase 5 (Cleanup & verify)
```

---

## Rollback

- Each task commits independently with passing tests
- If any step breaks, revert that single commit
- The pre-flight tasks (0a-0c) are safe to merge to main independently — they resolve cycles without structural changes

---

## Verification Checklist

After all tasks complete:

- [ ] `composer test` — all tests pass (core + laravel)
- [ ] `composer analyse` — PHPStan clean (level max, both packages)
- [ ] `vendor/bin/pint --test` — code style clean
- [ ] `php scripts/generate-docs.php` — docs regenerate without errors
- [ ] `docs/generated/layering.md` — zero violations
- [ ] `docs/generated/coupling-metrics.md` — Runner directory eliminated
- [ ] `docs/generated/public-api.md` — all namespaces correct
- [ ] `docs/generated/namespace-graph.md` — flat structure visible
- [ ] No `Runner\` namespace remains in any `use` statement
- [ ] `packages/core/src/Runner/` directory removed
