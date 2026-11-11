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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `RunCommand` gains `--parallel` option, default off.
- [ ] `--parallel` executes independent steps in the same level concurrently; an instrumented test observes overlapping execution.
- [ ] Dependent steps never start before their dependencies complete.
- [ ] Results are reported in original step order regardless of completion order.
- [ ] A cycle produces `runtime.dependency_cycle` and no requests are sent.
- [ ] Workflows with control-flow actions fall back to sequential execution and report the reason.
- [ ] The concurrency cap is enforced; a test with a blocking fake transport never exceeds it.
- [ ] Failure in one step cancels or drains siblings deterministically before the level ends.
- [ ] Default behaviour without `--parallel` is byte-for-byte equivalent to today.
- [ ] PHPStan max and Pint pass.
- [ ] **Core scheduler** is draft 50; this draft is the CLI port only.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive `--parallel` flag; new scheduler internal to `packages/runner` (draft 50 core).
- **Blast radius:** both execution paths if the async worker is touched; keep the change scoped to the sync executor plus the CLI flag initially.
- **Severity:** high complexity — concurrency bugs are hard to detect. Default off.
- **Rollback:** disable the flag; sequential behaviour remains the default.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No `--parallel` option | T1 | `RunCommand.php:45-51` | direct read |
| Steps execute one at a time | T1 | `WorkflowExecutor.php:116-152` | direct read |
| Effective dependencies are already computed | T1 | `DependencyGraph.php:31-45` | direct read |
| No parallel/concurrency code exists | T1 | grep for `parallel\|concurren\|Fiber` in `packages/runner/src packages/runtime/src` → no matches | executed grep |
| Draft 50 is the core dependency | T2 | draft 50 (DAG scheduler) | cross-ref |