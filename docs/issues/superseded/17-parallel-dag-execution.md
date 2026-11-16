# Draft issue 17 — Parallel execution of independent steps

- **Suggested title:** `[Feature] Execute independent steps concurrently with a DAG scheduler`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `runner`, `contracts`, `runtime`, `cli`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/runner` sync executor and async worker; `packages/contracts` `DependencyGraph`; `packages/runtime` state; `packages/cli` flag.
- **Arazzo spec version:** 1.0.x and 1.1.0.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core; PHP 8.4 has no native async, so concurrency must be explicit.
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P2 — large change with real risk; dependency analysis already exists.

### 📝 2. Problem Statement & Intent

`WorkflowExecutor::executeCanonically()` runs a strict sequential loop:

```php
$stepId = $this->firstStep($workflow);
while ($stepId !== null) {
    $step = $this->step($currentWorkflow, $stepId);
    [$stepContext, $success] = $this->stepExecutor->execute(...);
    // dispatch events, compute transition, move to next step
}
```

(`packages/runner/src/Sync/WorkflowExecutor.php:113-152`)

Every step waits for the previous step, even when there is no dependency. A
workflow that fetches a token, a config and a user profile in three independent
steps takes three round-trips. `DependencyGraph` already merges explicit
`dependsOn` and implicit output references into effective dependencies
(`packages/contracts/src/Dependency/DependencyGraph.php:31-45`), so the missing
piece is a scheduler, not analysis.

Expected: an opt-in `--parallel` mode executes independent steps concurrently
and falls back to sequential execution whenever control flow makes ordering
ambiguous.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Add `arazzo run --parallel`, default off.
- Build execution levels with Kahn's algorithm over `DependencyGraph`:
  level 0 = no dependencies; level N+1 = dependencies satisfied by 0..N.
- Execute steps within a level concurrently using a bounded worker pool; wait
  for the whole level before starting the next.
- Preserve deterministic reporting: results are ordered by original step index,
  not completion time.
- Detect cycles and report a stable `runtime.dependency_cycle` error.
- Sequential fallback (single-threaded) when any of the following is present:
  - `onSuccess` actions, except plain `end` with no parameters;
  - `goto` or `end` `onFailure` actions;
  - workflow-level `successActions` / `failureActions`;
  - `retry` that names a `stepId` or `workflowId`;
  - a sub-workflow step (the child may still parallelise internally);
  - a step selected by `--step` (draft 18).
- Emit `run.sequentialFallbacks[]` in the trace (draft 14) naming the workflow,
  step and reason.
- Concurrency cap defaults to a small number (e.g. 4) and is configurable; it
  interacts with draft 21's outbound rate limit.

**Non-goals**

- Changing the async queue worker's ordering guarantees.
- Fire-and-forget steps; every level is a barrier.
- Parallelising control-flow actions.
- A general-purpose async runtime or Fiber-based rewrite.

**Prior art in this repository**

- `DependencyGraph` computes topological order and cycles.
- `CliRunner` drains a `SyncQueueDriver`; an in-process worker pool can reuse
  the same job abstraction.
- The event ledger already timestamps events, so concurrency needs stable
  ordering only at the report layer.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Execute independent steps concurrently with a DAG scheduler, replacing the strict sequential loop in `WorkflowExecutor::executeCanonically()`.

**Architectural Boundary:** Bounded context = runner execution + dependency graph. Packages: `packages/runner` (sync executor and async worker), `packages/contracts` (`DependencyGraph`), `packages/runtime` (state), `packages/cli` (flag). Core DAG scheduler is draft 50; this draft is the CLI port. PHP 8.4 has no native async, so concurrency must be explicit.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Independent steps in the same level execute concurrently; bounded worker pool; effective concurrency is `min(schedulerConcurrency, maxConcurrent)`.
- [ ] **Flexible:** Concurrency cap configurable (default 4); sequential fallback when control flow makes ordering ambiguous; scheduler can be disabled.
- [ ] **Maintainable:** Kahn's algorithm over `DependencyGraph`; deterministic reporting ordered by original step index; scheduler internal to `packages/runner`.
- [ ] **Operable:** `--parallel` flag default off; cycle detection with `runtime.dependency_cycle`; failure in one step cancels/drains siblings deterministically.
- [ ] **Reliable:** Dependent steps never start before dependencies complete; results reported in original step order; concurrency cap enforced even with blocking transports.
- [ ] **Safe:** No fire-and-forget; every level is a barrier; sequential fallback for control-flow actions; no state corruption.
- [ ] **Secure:** No new authorization surface; concurrency controls protect against request floods when combined with draft 21 rate limiting.
- [ ] **Suitable:** Addresses the sequential bottleneck where independent steps wait unnecessarily; large change with real risk (P2).
- [ ] **Usable:** `--parallel` flag on `RunCommand`; default off preserves existing behaviour; `run.sequentialFallbacks[]` records fallback reasons.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: Independent steps execute concurrently**
- **Given** an Arazzo 1.1.0 document (`doc.arazzo`) containing workflow index 0 with three independent steps (step-1: `GET /items`, step-2: `GET /users`, step-3: `GET /config`; no `dependsOn` between them; no implicit output references); `DAGScheduler` (draft 50 core) is wired into `WorkflowExecutor`; `--parallel` is passed; concurrency cap is 4; an instrumented fake transport records request start/end timestamps.
- **When** `php bin/console arazzo run doc.arazzo --parallel` executes.
- **Then** steps in the same level (level 0, no dependencies) execute concurrently; the instrumented test observes overlapping execution (step-1 start < step-2 start AND step-1 end > step-2 start, proving overlap); `WorkflowExecutor` builds execution levels via Kahn's algorithm over `DependencyGraph`; all three steps complete.

**Scenario 2: Dependent steps wait for dependencies**
- **Given** an Arazzo document where step B (`stepId: "fetch-user"`) depends on step A (`stepId: "login"`) via `dependsOn: ["login"]` or implicit output reference (`$steps.login.outputs.token`); `--parallel` is used.
- **When** `DAGScheduler` builds execution levels (level 0: step A; level 1: step B).
- **Then** step B never starts before step A completes; step B's `startedAt` timestamp is after step A's `finishedAt` timestamp; `WorkflowExecutor` waits for the whole level before starting the next.

**Scenario 3: Results reported in original step order**
- **Given** concurrent execution where step-2 completes before step-1 (step-1 is slow, step-2 is fast); `--parallel` is used.
- **When** the run completes and results are collected.
- **Then** `run.steps[]` is reported in original step index order (step-1 first, then step-2), not completion order; `WorkflowExecutor` preserves deterministic ordering by original step index.

**Scenario 4: Cycle produces `runtime.dependency_cycle`**
- **Given** an Arazzo document with a dependency cycle (step A dependsOn step B, step B dependsOn step A); `--parallel` is used; `DependencyGraph` detects the cycle.
- **When** `DAGScheduler` attempts to build execution levels.
- **Then** `runtime.dependency_cycle` error is produced; `ErrorCollector` records the error under `errors`; no requests are sent; `ValidationResult::isValid()` returns `false`.

**Scenario 5: Control-flow actions trigger sequential fallback**
- **Given** an Arazzo document with control-flow actions present: `onSuccess` actions (other than plain `end`), `goto` actions, `end` onFailure actions, workflow-level `successActions`/`failureActions`, or `retry` naming another step/workflow; `--parallel` is used.
- **When** `DAGScheduler` evaluates whether parallel execution is safe.
- **Then** the workflow falls back to sequential execution; `run.sequentialFallbacks[]` records the reason (e.g., `"control-flow action: onSuccess goto"`); steps execute one at a time in document order.

**Scenario 6: Default behaviour unchanged without `--parallel`**
- **Given** `arazzo run doc.arazzo` without `--parallel`; `DAGScheduler` is disabled (default off).
- **When** the run executes.
- **Then** behaviour is byte-for-byte equivalent to today (strict sequential loop in `WorkflowExecutor::executeCanonically()`); `run.sequentialFallbacks[]` is empty; no concurrency is introduced.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `--parallel` executes independent steps in the same level concurrently; instrumented test observes overlapping execution
- [ ] Dependent steps never start before their dependencies complete
- [ ] Results reported in original step order regardless of completion order
- [ ] A cycle produces `runtime.dependency_cycle` and no requests are sent
- [ ] Workflows with control-flow actions fall back to sequential execution and report the reason
- [ ] A sub-workflow step triggers sequential fallback at the parent level while the child may parallelise
- [ ] Retry naming another step/workflow triggers sequential fallback
- [ ] The concurrency cap is enforced; a test with a blocking fake transport never exceeds it
- [ ] Failure in one step cancels or drains siblings deterministically before the level ends
- [ ] Default behaviour without `--parallel` is byte-for-byte equivalent to today
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] No telemetry/logging changes needed beyond existing event ledger timestamps
- [ ] Core scheduler (DAG scheduler) is draft 50; this draft is the CLI port only

### 🧬 10. Source Grounding & Code Location

- `packages/runner/src/Sync/WorkflowExecutor.php:113-165` — sequential loop and transitions.
- `packages/contracts/src/Dependency/DependencyGraph.php:31-45` — effective dependencies.
- `packages/runner/src/Sync/WorkflowEngine.php` — control-flow transitions that force sequential fallback.
- `packages/runner/src/Execution/SyncQueueDriver.php` — existing job drain.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/runner/src/Sync/WorkflowExecutor.php#L113-L152"><code>WorkflowExecutor.php:113-152</code></a> @ <code>ea8c55a</code></summary>

```php
$stepId = $this->firstStep($workflow);
$results = [];
try {
    while ($stepId !== null) {
        $step = $this->step($currentWorkflow, $stepId);
        // ...
        [$stepContext, $success] = $this->stepExecutor->execute(StepParameterMerger::merge($step, $currentWorkflow), $state->toContext(), $document);
        // ...
        $transition = $this->workflowEngine->transition($document, $currentWorkflow, $step, $state, $success);
        // ...
    }
}
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive `--parallel` flag; new scheduler internal to `packages/runner`.
- **Blast radius:** both execution paths if the async worker is touched; keep the change scoped to the sync executor plus the CLI flag initially.
- **Severity:** high complexity — concurrency bugs are hard to detect. Default off.
- **Rollback:** disable the flag; sequential behaviour remains the default.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Steps execute one at a time | T1 | `WorkflowExecutor.php:116-152` | direct read |
| Effective dependencies are already computed | T1 | `DependencyGraph.php:31-45` | direct read |
| No parallel/concurrency code exists | T1 | `grep -rIn -i 'parallel\|concurren\|Fiber' packages/runner/src packages/runtime/src` → no matches | executed |
| Control-flow actions make ordering ambiguous | T1 | `WorkflowEngine` transition types | direct read |
| A bounded in-process pool is feasible | T2 | `SyncQueueDriver` already queues discrete jobs | inferred |
