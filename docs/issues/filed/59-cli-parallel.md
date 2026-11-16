# Draft issue 59 — CLI: `run --parallel` concurrent step execution

- **Suggested title:** `[Feature] `arazzo run --parallel` execute independent steps concurrently`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `cli`, `runner`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `RunCommand` `--parallel` option; `packages/runner` DAG scheduler (draft 50 core).
- **Arazzo spec version:** 1.0.x and 1.1.0.
- **PHP target:** 8.4.
- **Ecosystem:** CLI entry point only; the core scheduler lives in draft 50.
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P2 — large change with real risk; dependency analysis already exists.

### 📝 2. Problem Statement & Intent

Draft 50 core adds a DAG scheduler that executes independent steps concurrently. This draft exposes it via the CLI:

- `WorkflowExecutor::executeCanonically()` runs a strict sequential loop (`packages/runner/src/Sync/WorkflowExecutor.php:113-152`). Every step waits for the previous step, even when there is no dependency.
- `DependencyGraph` already merges explicit `dependsOn` and implicit output references into effective dependencies, so the missing piece at the CLI level is just the flag.

Expected: `arazzo run --parallel` (default off) executes independent steps concurrently using the core DAG scheduler, with sequential fallback when control flow makes ordering ambiguous.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `arazzo run --parallel`, default off.
- Build execution levels with Kahn's algorithm over `DependencyGraph` (draft 50 core).
- Execute steps within a level concurrently using a bounded worker pool; wait for the whole level before starting the next.
- Preserve deterministic reporting: results are ordered by original step index, not completion time.
- Sequential fallback when control-flow actions are present (`onSuccess` actions except plain `end`, `goto`/`end` onFailure, workflow-level actions, retry naming another step/workflow, sub-workflow steps, `--step` selection).
- Concurrency cap defaults to a small number (e.g. 4) and is configurable; it interacts with draft 62 core's outbound rate limit.
- Core DAG scheduler — that is draft 50.

**Non-goals**

- Changing the async queue worker's ordering guarantees.
- Fire-and-forget steps; every level is a barrier.
- Parallelising control-flow actions.
- A general-purpose async runtime or Fiber-based rewrite.

**Prior art in this repository**

- `DependencyGraph` computes topological order and cycles.
- `CliRunner` drains a `SyncQueueDriver`; an in-process worker pool can reuse the same job abstraction.
- The event ledger already timestamps events, so concurrency needs stable ordering only at the report layer.
- Draft 50 core: DAG scheduler.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Expose core DAG scheduler via CLI flag so `arazzo run --parallel` executes independent steps concurrently.

**Architectural Boundary:** Bounded context = CLI `run` command concurrency option. Packages: `packages/cli` (`RunCommand` `--parallel` option), `packages/runner` (DAG scheduler — draft 50 core). CLI port only; core scheduler is draft 50.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Independent steps in same level execute concurrently; bounded worker pool; effective concurrency `min(schedulerConcurrency, maxConcurrent)`.
- [ ] **Flexible:** `--parallel` default off; concurrency cap configurable; sequential fallback for control-flow actions.
- [ ] **Maintainable:** Additive CLI option; scheduler internal to `packages/runner`; deterministic reporting by original step index.
- [ ] **Operable:** Default off; cycle detection `runtime.dependency_cycle`; rollback by disabling flag (sequential is default).
- [ ] **Reliable:** Dependent steps never start before dependencies complete; results in original step order; concurrency cap enforced.
- [ ] **Safe:** No fire-and-forget; every level is a barrier; sequential fallback for control-flow actions.
- [ ] **Secure:** No new authorization surface; concurrency controls protect against request floods (with draft 21 rate limiting).
- [ ] **Suitable:** Addresses sequential bottleneck; large change (P2); default off to manage risk.
- [ ] **Usable:** `--parallel` flag; default preserves existing behavior.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `--parallel` default off**
- **Given** `arazzo run doc.yaml` without `--parallel`; `DAGScheduler` (draft 50 core) is disabled (default off); `WorkflowExecutor::executeCanonically()` runs the strict sequential loop.
- **When** `php bin/console arazzo run doc.yaml` executes without `--parallel`.
- **Then** sequential execution (byte-for-byte equivalent to today); `run.sequentialFallbacks[]` is empty; no concurrency is introduced; existing tests pass without modification.

**Scenario 2: `--parallel` executes independent steps concurrently**
- **Given** an Arazzo document (`doc.arazzo`) containing workflow index 0 with three independent steps (step-1: `GET /items`, step-2: `GET /users`, step-3: `GET /config`; no `dependsOn` between them); `--parallel` is passed; concurrency cap is 4; an instrumented fake transport records request start/end timestamps.
- **When** `php bin/console arazzo run doc.arazzo --parallel` executes.
- **Then** steps in the same level (level 0, no dependencies) execute concurrently; the instrumented test observes overlapping execution (step-1 start < step-2 start AND step-1 end > step-2 start, proving overlap); `DAGScheduler` builds execution levels via Kahn's algorithm over `DependencyGraph`; all three steps complete.

**Scenario 3: Dependent steps wait for dependencies**
- **Given** an Arazzo document where step B (`stepId: "fetch-user"`) depends on step A (`stepId: "login"`) via `dependsOn: ["login"]` or implicit output reference (`$steps.login.outputs.token`); `--parallel` is used.
- **When** `DAGScheduler` builds execution levels (level 0: step A; level 1: step B).
- **Then** step B never starts before step A completes; step B's `startedAt` timestamp is after step A's `finishedAt` timestamp; `WorkflowExecutor` waits for the whole level before starting the next.

**Scenario 4: Results in original step order**
- **Given** concurrent execution where step-2 completes before step-1 (step-1 is slow, step-2 is fast); `--parallel` is used.
- **When** the run completes and results are collected.
- **Then** `run.steps[]` is reported in original step index order (step-1 first, then step-2), not completion order; `WorkflowExecutor` preserves deterministic ordering by original step index.

**Scenario 5: Cycle produces `runtime.dependency_cycle`**
- **Given** an Arazzo document with a dependency cycle (step A `dependsOn: ["B"]`, step B `dependsOn: ["A"]`); `--parallel` is used; `DependencyGraph` detects the cycle.
- **When** `DAGScheduler` attempts to build execution levels.
- **Then** `runtime.dependency_cycle` error is produced; `ErrorCollector` records the error under `errors`; no requests are sent; `ValidationResult::isValid()` returns `false`.

**Scenario 6: Control-flow triggers sequential fallback**
- **Given** an Arazzo document with control-flow actions present: `onSuccess` actions (other than plain `end`), `goto` actions, `end` onFailure actions, workflow-level `successActions`/`failureActions`, or `retry` naming another step/workflow; `--parallel` is used.
- **When** `DAGScheduler` evaluates whether parallel execution is safe.
- **Then** the workflow falls back to sequential execution; `run.sequentialFallbacks[]` records the reason (e.g., `"control-flow action: onSuccess goto"`); steps execute one at a time in document order.

**Scenario 7: Concurrency cap enforced**
- **Given** blocking fake transport; concurrency cap = 4; `--parallel` is used with 10 independent steps; `SyncQueueDriver` enforces the cap at the job drain level.
- **When** 10 independent steps execute concurrently with `--parallel`.
- **Then** never more than 4 requests are in-flight at any time; steps 5-10 wait for a permit before sending; the semaphore enforces the cap; each step acquires a semaphore permit before sending and releases it after response/failure.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `RunCommand` gains `--parallel` option, default off
- [ ] `--parallel` executes independent steps in same level concurrently
- [ ] Dependent steps never start before dependencies complete
- [ ] Results reported in original step order regardless of completion order
- [ ] Cycle produces `runtime.dependency_cycle`; no requests sent
- [ ] Workflows with control-flow actions fall back to sequential and report reason
- [ ] Concurrency cap enforced; blocking fake transport never exceeds it
- [ ] Failure in one step cancels/drains siblings deterministically
- [ ] Default behavior byte-for-byte equivalent to today
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Core scheduler (DAG scheduler) is draft 50; this draft is CLI port only

### 🧬 10. Source Grounding & Code Location

- `packages/cli/src/Console/Command/RunCommand.php:45-51` — no `--parallel` option.
- `packages/runner/src/Sync/WorkflowExecutor.php:113-165` — sequential loop and transitions.
- `packages/contracts/src/Dependency/DependencyGraph.php:31-45` — effective dependencies.
- Draft 50 core: DAG scheduler.

<details><summary><code>RunCommand.php:45-51</code></summary>

```php
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to an Arazzo YAML/JSON document')
            ->addOption('workflow', 'w', InputOption::VALUE_REQUIRED, 'workflowId to run (defaults to the first workflow)')
            ->addOption('input', 'i', InputOption::VALUE_REQUIRED, 'Workflow inputs as inline JSON or @file.json');
    }
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive `--parallel` flag; new scheduler internal to `packages/runner` (draft 50 core).
- **Blast radius:** both execution paths if the async worker is touched; keep the change scoped to the sync executor plus the CLI flag initially.
- **Severity:** high complexity — concurrency bugs are hard to detect. Default off.
- **Rollback:** disable the flag; sequential behaviour remains the default.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No `--parallel` option | T1 | `RunCommand.php:45-51` | direct read |
| Steps execute one at a time | T1 | `WorkflowExecutor.php:116-152` | direct read |
| Effective dependencies are already computed | T1 | `DependencyGraph.php:31-45` | direct read |
| No parallel/concurrency code exists | T1 | grep for `parallel\|concurren\|Fiber` in `packages/runner/src packages/runtime/src` → no matches | executed grep |
| Draft 50 is the core dependency | T2 | draft 50 (DAG scheduler) | cross-ref |