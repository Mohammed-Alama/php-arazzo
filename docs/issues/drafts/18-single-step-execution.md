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

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Provide `arazzo run --step <id>` to execute one step with its upstream dependencies, so authors can debug a failing step without re-running the entire workflow.

**Architectural Boundary:** Bounded context = runner execution + dependency graph. Packages: `packages/cli` (`RunCommand`), `packages/runner` (executor), `packages/contracts` (`DependencyGraph`). Core single-step mode is draft 51; this draft is the CLI port.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Executes only the target step and its transitive upstream dependencies; no steps after the target are executed.
- [ ] **Flexible:** `--no-deps` executes only the target; `--step` is mutually exclusive with `--parallel`; composes with `--dry-run`, `--trace`, `--json`.
- [ ] **Maintainable:** Uses `DependencyGraph::getEffectiveDependencies()` for transitive deps; single-responsibility execution mode; result distinguishes dependency steps from target.
- [ ] **Operable:** Unknown step id fails before any request with a clear error listing valid ids; no change to full-run behaviour when options absent.
- [ ] **Reliable:** Steps execute in dependency order; no-deps mode fails with `step.missing_dependency_output` before sending if upstream output unavailable.
- [ ] **Safe:** No side effects beyond the target step and its dependencies; success criteria run normally on dependency steps.
- [ ] **Secure:** No new authorization surface; input validation (draft 19) applies to `--input`.
- [ ] **Suitable:** High-value debugging aid; avoids repeating side effects when iterating on a single failing step.
- [ ] **Usable:** `--step <id>` and `--no-deps` flags on `RunCommand`; clear distinction between dependency steps and target step in the report.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `--step <id>` executes target and upstream dependencies, then stops**
- **Given** an Arazzo 1.1.0 document (`doc.arazzo`) containing workflow index 0 with a linear chain of steps: step-1 (`stepId: "login"`), step-2 (`stepId: "fetch-config"`, dependsOn: `["login"]`), step-3 (`stepId: "create-order"`, dependsOn: `["fetch-config"]`); `SingleStepExecutionMode` (draft 51 core) is wired into `WorkflowExecutor`; `DependencyGraph::getEffectiveDependencies()` returns transitive dependencies; `--step step3` is passed.
- **When** `php bin/console arazzo run doc.arazzo --step create-order` executes.
- **Then** steps 1, 2, and 3 execute in dependency order (login → fetch-config → create-order); `DependencyGraph` computes transitive upstream dependencies; no step after step 3 (create-order) is executed; `SingleStepExecutionMode` stops after the target step.

**Scenario 2: `--no-deps` executes only the target**
- **Given** `--step step3 --no-deps` is passed; step-3 depends on step-2's output but `--no-deps` skips dependency execution.
- **When** `SingleStepExecutionMode` executes only the target step.
- **Then** only step-3 (create-order) executes; upstream dependencies (login, fetch-config) are not executed; step-3's unavailable upstream outputs trigger `step.missing_dependency_output` if not supplied via `--input`.

**Scenario 3: `--no-deps` with unavailable upstream output fails**
- **Given** `--step step3 --no-deps` where step-3 depends on step-2's output (`$steps.fetch-config.outputs.endpoint`); step-2 has not been executed (no-deps mode); no `--input` supplies the missing value.
- **When** `SingleStepExecutionMode` attempts to compile step-3's request.
- **Then** it fails with error code `step.missing_dependency_output` before sending step-3's request; `ErrorCollector` records the error under `errors`; the error message identifies the missing dependency output; `ValidationResult::isValid()` returns `false`.

**Scenario 4: Unknown step id fails before any request**
- **Given** `--step nonexistent` is passed; the document contains steps `login`, `fetch-config`, `create-order`; none match `nonexistent`.
- **When** `SingleStepExecutionMode` validates the step ID before any request.
- **Then** it fails before any request is sent; `ErrorCollector` records an error listing valid step IDs (`login`, `fetch-config`, `create-order`); `ValidationResult::isValid()` returns `false`; no transport call is made.

**Scenario 5: `--step` with `--parallel` runs sequentially**
- **Given** `--step step3 --parallel` is passed; only one step (step-3) is selected for execution; `--parallel` would normally enable concurrency but a single step cannot be parallelized.
- **When** `SingleStepExecutionMode` executes with `--parallel` also set.
- **Then** execution is sequential (single step runs sequentially); `run.sequentialFallbacks[]` records the reason (`"--step implies sequential execution"`); `--parallel` has no effect for a single step.

**Scenario 6: Report distinguishes dependency steps from target**
- **Given** `--step step3` with dependencies step-1 (login) and step-2 (fetch-config); step-3 is the target (create-order).
- **When** the run completes and the result is serialized.
- **Then** the report marks step-1 and step-2 as `"dependency": true` and step-3 as `"target": true`; `run.steps[]` distinguishes dependency steps from the target step; `SingleStepExecutionMode` populates the `executionMode` field in the step result.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `RunCommand` gains `--step <id>` and `--no-deps` options
- [ ] `--step <id>` executes the target and its transitive upstream dependencies in dependency order, then stops
- [ ] No step after the target is executed
- [ ] `--no-deps` executes only the target
- [ ] `--no-deps` with an unavailable upstream output fails with `step.missing_dependency_output` before sending the target request
- [ ] An unknown step id fails before any request and lists valid ids
- [ ] `--step` combined with `--parallel` runs sequentially and reports why
- [ ] The report distinguishes dependency steps from the target step
- [ ] The command composes with `--dry-run`, `--trace` and `--json`
- [ ] Tests cover a linear workflow, a diamond dependency, a missing dependency and an unknown id
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] No telemetry/logging changes needed beyond existing error handling
- [ ] Core mode (single-step execution) is draft 51; this draft is the CLI port only

### 🧬 10. Source Grounding & Code Location

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

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive CLI options.
- **Blast radius:** `packages/cli`, `packages/runner` entry point; sequential behaviour unchanged when the options are absent.
- **Severity:** low — debugging convenience, no semantic change to full runs.
- **Rollback:** remove the options.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Execution always starts at the first step | T1 | `WorkflowExecutor.php:113` | direct read |
| No `--step` option exists | T1 | `RunCommand.php:45-51` | direct read |
| Dependency resolution is available | T1 | `DependencyGraph::getEffectiveDependencies()` | direct read |
| Resuming a subset of steps is already possible | T1 | `CliRunner.php:138-170` | direct read |
| Missing dependency outputs can be detected before the request | T2 | expression resolution happens during request compilation | inferred |
