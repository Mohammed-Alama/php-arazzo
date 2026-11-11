# Draft issue 18 — `arazzo run --step` single-step execution

- **Suggested title:** `[Feature] `arazzo run --step <id>` — execute one step with dependency resolution`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `cli`, `runner`, `contracts`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `RunCommand`; `packages/runner` executor; `packages/contracts` `DependencyGraph`.
- **Arazzo spec version:** independent of spec version.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core CLI.
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P2 — small, high-value debugging aid.

### 📝 2. Problem Statement & Intent

`arazzo run` always begins at `firstStep($workflow)` and runs until a terminal
transition (`packages/runner/src/Sync/WorkflowExecutor.php:113-165`). There is no
way to execute one step. When a single step fails, the author must re-run the
whole workflow — repeating every side effect — to iterate on it.

Expected: `arazzo run --step <step-id>` executes the selected step and the
upstream steps it depends on, then stops. `--no-deps` executes only the selected
step, using supplied inputs and recorded/step outputs where available.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `arazzo run <file> --step <step-id>` executes the target step plus its
  transitive upstream dependencies from `DependencyGraph`, in order, and stops
  after the target.
- `--no-deps` executes only the target step. Steps whose outputs are unavailable
  fail with a stable `step.missing_dependency_output` error unless the value is
  supplied through `--input`, `--step-output` or a trace/replay source.
- `--step` is mutually exclusive with `--parallel` (a single step runs
  sequentially; report the fallback).
- The run report marks which steps were executed because they are dependencies
  and which was the target.
- An unknown step id fails before any request with a stable error listing valid
  ids.
- Works with `--dry-run` (draft 16), `--trace` (draft 14) and `--json` (draft 12).

**Non-goals**

- Resuming a previous failed run (that is `CliRunner::resume()`).
- Replaying recorded responses (draft 15).
- Skipping success criteria on dependency steps; they run normally.

**Prior art in this repository**

- `DependencyGraph::getEffectiveDependencies()` already returns transitive
  dependencies.
- `CliRunner::resume()` already dispatches a subset of steps from a context.
- `StepOutcomeHandler` records step outputs that a later isolated step can read
  from persisted state.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `--step <id>` executes the target and its transitive upstream dependencies in dependency order, then stops.
- [ ] No step after the target is executed.
- [ ] `--no-deps` executes only the target.
- [ ] `--no-deps` with an unavailable upstream output fails with `step.missing_dependency_output` before sending the target request.
- [ ] An unknown step id fails before any request and lists valid ids.
- [ ] `--step` combined with `--parallel` runs sequentially and reports why.
- [ ] The report distinguishes dependency steps from the target step.
- [ ] The command composes with `--dry-run`, `--trace` and `--json`.
- [ ] Tests cover a linear workflow, a diamond dependency, a missing dependency and an unknown id.
- [ ] PHPStan max and Pint pass.

### 🧬 7. Source Grounding & Code Location

- `packages/runner/src/Sync/WorkflowExecutor.php:113-165` — always starts at the first step and runs to terminal.
- `packages/contracts/src/Dependency/DependencyGraph.php` — effective dependencies and topological order.
- `packages/cli/src/Console/Cli/CliRunner.php:138-170` — precedent for dispatching a subset of steps.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/runner/src/Sync/WorkflowExecutor.php#L112-L118"><code>WorkflowExecutor.php:112-118</code></a> @ <code>ea8c55a</code></summary>

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

- **Public API impact:** additive CLI options.
- **Blast radius:** `packages/cli`, `packages/runner` entry point; sequential behaviour unchanged when the options are absent.
- **Severity:** low — debugging convenience, no semantic change to full runs.
- **Rollback:** remove the options.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Execution always starts at the first step | T1 | `WorkflowExecutor.php:113` | direct read |
| No `--step` option exists | T1 | `RunCommand.php:45-51` | direct read |
| Dependency resolution is available | T1 | `DependencyGraph::getEffectiveDependencies()` | direct read |
| Resuming a subset of steps is already possible | T1 | `CliRunner.php:138-170` | direct read |
| Missing dependency outputs can be detected before the request | T2 | expression resolution happens during request compilation | inferred |
