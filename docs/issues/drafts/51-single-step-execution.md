# Draft issue 51 — Single-step execution mode

- **Suggested title:** `[Feature] Core single-step execution mode that runs one step and its upstream dependencies`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `runner`, `contracts`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/runner` executor; `packages/contracts` `DependencyGraph`.
- **Arazzo spec version:** independent of spec version.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core; port-agnostic (usable from CLI, MCP, or UI).
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P2 — small, high-value debugging aid.

### 📝 2. Problem Statement & Intent

`WorkflowExecutor` always begins at `firstStep($workflow)` and runs until a terminal transition (`packages/runner/src/Sync/WorkflowExecutor.php:113-165`). There is no core way to execute one step. When a single step fails, the author must re-run the whole workflow — repeating every side effect — to iterate on it.

Expected: a core single-step execution mode that executes the selected step and the upstream steps it depends on, then stops. A `--no-deps` equivalent executes only the selected step, using supplied inputs and recorded/step outputs where available.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Execute the target step plus its transitive upstream dependencies from `DependencyGraph`, in order, then stop after the target.
- Execute only the target step (no-deps mode). Steps whose outputs are unavailable fail with a stable `step.missing_dependency_output` error unless the value is supplied through inputs, recorded outputs or a trace/replay source.
- The run result marks which steps were executed because they are dependencies and which was the target.
- An unknown step id fails before any request with a stable error listing valid ids.
- Composes with dry-run (draft 49 core), trace (draft 47 core) and run report (draft 45 core).

**Non-goals**

- Resuming a previous failed run (that is `CliRunner::resume()`).
- Replaying recorded responses (draft 48 core).
- Skipping success criteria on dependency steps; they run normally.
- CLI flag (`--step`, `--no-deps`) — tracked separately in draft 60.

**Prior art in this repository**

- `DependencyGraph::getEffectiveDependencies()` already returns transitive dependencies.
- `CliRunner::resume()` already dispatches a subset of steps from a context.
- `StepOutcomeHandler` records step outputs that a later isolated step can read from persisted state.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] Single-step mode executes the target and its transitive upstream dependencies in dependency order, then stops.
- [ ] No step after the target is executed.
- [ ] No-deps mode executes only the target.
- [ ] No-deps mode with an unavailable upstream output fails with `step.missing_dependency_output` before sending the target request.
- [ ] An unknown step id fails before any request and lists valid ids.
- [ ] The result distinguishes dependency steps from the target step.
- [ ] `SingleStepTest` fixture-tests a linear workflow, a diamond dependency, a missing dependency and an unknown id.
- [ ] PHPStan max and Pint pass.
- [ ] **CLI port** (`--step <id>`, `--no-deps` on `RunCommand`) is tracked separately in draft 60.

### 🧬 7. Source Grounding & Code Location

- `packages/runner/src/Sync/WorkflowExecutor.php:113-165` — always starts at the first step and runs to terminal.
- `packages/contracts/src/Dependency/DependencyGraph.php` — effective dependencies and topological order.
- `packages/cli/src/Console/Cli/CliRunner.php:138-170` — precedent for dispatching a subset of steps.

<details><summary><code>WorkflowExecutor.php:113-118</code></summary>

```php
$currentWorkflow = $workflow;
$stepId = $this->firstStep($workflow);
$results = [];
try {
    while ($stepId !== null) {
        $step = $this->step($currentWorkflow, $stepId);
```

</details>

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive execution mode; sequential behaviour unchanged when the mode is absent.
- **Blast radius:** `packages/cli`, `packages/runner` entry point; low — debugging convenience, no semantic change to full runs.
- **Severity:** low — debugging convenience, no semantic change to full runs.
- **Rollback:** remove the mode; full-run behaviour unchanged.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Execution always starts at the first step | T1 | `WorkflowExecutor.php:113` | direct read |
| Dependency resolution is available | T1 | `DependencyGraph::getEffectiveDependencies()` | direct read |
| Resuming a subset of steps is already possible | T1 | `CliRunner.php:138-170` | direct read |
| Missing dependency outputs can be detected before the request | T2 | expression resolution happens during request compilation | inferred |