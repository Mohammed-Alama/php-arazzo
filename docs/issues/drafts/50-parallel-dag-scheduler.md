# Draft issue 50 — Parallel DAG scheduler

- **Suggested title:** `[Feature] Core DAG scheduler that executes independent steps concurrently`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `runner`, `contracts`, `runtime`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/runner` sync executor; `packages/contracts` `DependencyGraph`; `packages/runtime` state.
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

Every step waits for the previous step, even when there is no dependency. A workflow that fetches a token, a config and a user profile in three independent steps takes three round-trips. `DependencyGraph` already merges explicit `dependsOn` and implicit output references into effective dependencies (`packages/contracts/src/Dependency/DependencyGraph.php:31-45`), so the missing piece is a scheduler, not analysis.

Expected: a core DAG scheduler that executes independent steps concurrently and falls back to sequential execution whenever control flow makes ordering ambiguous.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Build execution levels with Kahn's algorithm over `DependencyGraph`:
  - level 0 = no dependencies; level N+1 = dependencies satisfied by 0..N.
- Execute steps within a level concurrently using a bounded worker pool; wait for the whole level before starting the next.
- Preserve deterministic reporting: results are ordered by original step index, not completion time.
- Detect cycles and report a stable `runtime.dependency_cycle` error.
- Sequential fallback (single-threaded) when any of the following is present:
  - `onSuccess` actions, except plain `end` with no parameters;
  - `goto` or `end` `onFailure` actions;
  - workflow-level `successActions` / `failureActions`;
  - `retry` that names a `stepId` or `workflowId`;
  - a sub-workflow step (the child may still parallelise internally).
- Emit `run.sequentialFallbacks[]` naming the workflow, step and reason (consumed by trace serializer, draft 47).
- Concurrency cap defaults to a small number (e.g. 4) and is configurable; it interacts with draft 53 core's outbound rate limit.

**Non-goals**

- Changing the async queue worker's ordering guarantees.
- Fire-and-forget steps; every level is a barrier.
- Parallelising control-flow actions.
- A general-purpose async runtime or Fiber-based rewrite.
- CLI flag (`--parallel`) — tracked separately in draft 59.

**Prior art in this repository**

- `DependencyGraph` computes topological order and cycles.
- `CliRunner` drains a `SyncQueueDriver`; an in-process worker pool can reuse the same job abstraction.
- The event ledger already timestamps events, so concurrency needs stable ordering only at the report layer.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] Scheduler executes independent steps in the same level concurrently; an instrumented test observes overlapping execution.
- [ ] Dependent steps never start before their dependencies complete.
- [ ] Results are reported in original step order regardless of completion order.
- [ ] A cycle produces `runtime.dependency_cycle` and no requests are sent.
- [ ] Workflows with control-flow actions fall back to sequential execution and report the reason.
- [ ] A sub-workflow step triggers sequential fallback at the parent level while the child may parallelise.
- [ ] Retry naming another step/workflow triggers sequential fallback.
- [ ] The concurrency cap is enforced; a test with a blocking fake transport never exceeds it.
- [ ] Failure in one step cancels or drains siblings deterministically before the level ends; the resulting error identifies the failing step.
- [ ] Default behaviour without the scheduler is byte-for-byte equivalent to today.
- [ ] `ParallelSchedulerTest` fixture-tests a diamond dependency, control-flow fallback, cycle detection and cap enforcement.
- [ ] PHPStan max and Pint pass.
- [ ] **CLI port** (`--parallel` flag on `RunCommand`) is tracked separately in draft 59.

### 🧬 7. Source Grounding & Code Location

- `packages/runner/src/Sync/WorkflowExecutor.php:113-165` — sequential loop and transitions.
- `packages/contracts/src/Dependency/DependencyGraph.php:31-45` — effective dependencies.
- `packages/runner/src/Sync/WorkflowEngine.php` — control-flow transitions that force sequential fallback.
- `packages/runner/src/Execution/SyncQueueDriver.php` — existing job drain.

<details><summary><code>WorkflowExecutor.php:113-152</code></summary>

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive scheduler internal to `packages/runner`; default off.
- **Blast radius:** both execution paths if the async worker is touched; keep the change scoped to the sync executor initially.
- **Severity:** high complexity — concurrency bugs are hard to detect. Default off.
- **Rollback:** disable the scheduler; sequential behaviour remains the default.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Steps execute one at a time | T1 | `WorkflowExecutor.php:116-152` | direct read |
| Effective dependencies are already computed | T1 | `DependencyGraph.php:31-45` | direct read |
| No parallel/concurrency code exists | T1 | grep for `parallel\|concurren\|Fiber` in `packages/runner/src packages/runtime/src` → no matches | executed grep |
| Control-flow actions make ordering ambiguous | T1 | `WorkflowEngine` transition types | direct read |
| A bounded in-process pool is feasible | T2 | `SyncQueueDriver` already queues discrete jobs | inferred |