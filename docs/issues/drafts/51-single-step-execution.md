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

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Provide a core single-step execution mode that executes the selected step and its upstream dependencies, then stops — enabling debugging of individual steps without re-running the entire workflow.

**Architectural Boundary:** Bounded context = runner execution + dependency graph. Packages: `packages/runner` (executor), `packages/contracts` (`DependencyGraph`). Port-agnostic; CLI port tracked in draft 60.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Executes only target + transitive upstream dependencies; no steps after target executed.
- [ ] **Flexible:** `--no-deps` executes only target; `--step` mutually exclusive with `--parallel`; composes with `--dry-run`, `--trace`, `--json`.
- [ ] **Maintainable:** Uses `DependencyGraph::getEffectiveDependencies()`; result distinguishes dependency steps from target; single-responsibility execution mode.
- [ ] **Operable:** Unknown step id fails before any request with valid ids listed; no change to full-run behavior when options absent.
- [ ] **Reliable:** Steps execute in dependency order; no-deps mode fails with `step.missing_dependency_output` before sending if upstream output unavailable.
- [ ] **Safe:** No side effects beyond target step and dependencies; success criteria run normally on dependency steps.
- [ ] **Secure:** No new authorization surface; input validation (draft 52) applies to `--input`.
- [ ] **Suitable:** High-value debugging aid; avoids repeating side effects when iterating on a single failing step.
- [ ] **Usable:** `--step <id>` and `--no-deps` options; clear distinction between dependency steps and target step in result.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `--step <id>` executes target and upstream dependencies, then stops**
- **Given** an Arazzo 1.1.0 document (`doc.arazzo`) containing workflow index 0 with a linear chain of steps: step-1 (`stepId: "login"`), step-2 (`stepId: "fetch-config"`, dependsOn: `["login"]`), step-3 (`stepId: "create-order"`, dependsOn: `["fetch-config"]`); `SingleStepExecutionMode` (draft 51 core) is wired into `WorkflowExecutor`; `DependencyGraph::getEffectiveDependencies()` returns transitive dependencies; `--step step3` is passed.
- **When** `php bin/console arazzo run doc.arazzo --step create-order` executes.
- **Then** steps 1, 2, and 3 execute in dependency order (login → fetch-config → create-order); `DependencyGraph` computes transitive upstream dependencies; no step after step 3 (create-order) is executed; `SingleStepExecutionMode` stops after the target step; `RunnerFacade::execute()` returns results for steps 1-3 only.

**Scenario 2: No step after target executed**
- **Given** `--step step2` in a 5-step workflow (step-1 through step-5); `--step step2` is passed; `SingleStepExecutionMode` is active.
- **When** `php bin/console arazzo run doc.arazzo --step step2` executes.
- **Then** steps 3, 4, 5 are not executed; only steps 1-2 run (step-2 is the target, step-1 is its dependency); `RunnerFacade::execute()` returns results for steps 1-2 only.

**Scenario 3: `--no-deps` executes only target**
- **Given** `--step step3 --no-deps` is passed; step-3 depends on step-2's output but `--no-deps` skips dependency execution; `SingleStepExecutionMode` executes only the target step.
- **When** `php bin/console arazzo run doc.arazzo --step step3 --no-deps` executes.
- **Then** only step-3 executes; upstream dependencies (step-1, step-2) are not executed; step-3's unavailable upstream output triggers `step.missing_dependency_output` if not supplied via `--input`.

**Scenario 4: `--no-deps` with unavailable upstream output fails**
- **Given** `--step step3 --no-deps` where step-3 depends on step-2's output (`$steps.fetch-config.outputs.endpoint`); step-2 has not been executed (no-deps mode); no `--input` supplies the missing value.
- **When** `SingleStepExecutionMode` attempts to compile step-3's request.
- **Then** it fails with error code `step.missing_dependency_output` before sending step-3's request; `ErrorCollector` records the error under `errors`; the error message identifies the missing dependency output; `ValidationResult::isValid()` returns `false`; no HTTP request is dispatched for step-3.

**Scenario 5: Unknown step id fails before any request**
- **Given** `--step nonexistent` is passed; the document contains steps `login`, `fetch-config`, `create-order`; none match `nonexistent`; `SingleStepExecutionMode` validates step ID before any request.
- **When** `php bin/console arazzo run doc.arazzo --step nonexistent` executes.
- **Then** it fails before any request is sent; `ErrorCollector` records an error listing valid step IDs (`login`, `fetch-config`, `create-order`); `ValidationResult::isValid()` returns `false`; no transport call is made.

**Scenario 6: `--step` + `--parallel` runs sequentially**
- **Given** `--step step3 --parallel` is passed; only one step (step-3) is selected for execution; `--parallel` would normally enable concurrency but a single step cannot be parallelized.
- **When** `php bin/console arazzo run doc.arazzo --step step3 --parallel` executes.
- **Then** execution is sequential (single step runs sequentially); `run.sequentialFallbacks[]` records the reason (`"--step implies sequential execution"`); `--parallel` has no effect for a single step.

**Scenario 7: Report distinguishes dependency steps from target**
- **Given** `--step step3` with dependencies step-1 (login) and step-2 (fetch-config); step-3 is the target (create-order); `SingleStepExecutionMode` populates the `executionMode` field in the step result.
- **When** `php bin/console arazzo run doc.arazzo --step step3` executes and the result is serialized.
- **Then** the report marks step-1 and step-2 as `"dependency": true` and step-3 as `"target": true`; `run.steps[]` distinguishes dependency steps from the target step; `RunnerFacade::execute()` returns the distinguished results.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] Single-step mode executes target and transitive upstream dependencies in dependency order, then stops
- [ ] No step after target executed
- [ ] `--no-deps` executes only target
- [ ] `--no-deps` with unavailable upstream output fails with `step.missing_dependency_output` before sending target request
- [ ] Unknown step id fails before any request and lists valid ids
- [ ] Result distinguishes dependency steps from target step
- [ ] `SingleStepTest` fixture-tests linear workflow, diamond dependency, missing dependency, unknown id
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] CLI port (`--step <id>`, `--no-deps` on `RunCommand`) tracked separately in draft 60

### 🧬 10. Source Grounding & Code Location

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

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive execution mode; sequential behaviour unchanged when the mode is absent.
- **Blast radius:** `packages/cli`, `packages/runner` entry point; low — debugging convenience, no semantic change to full runs.
- **Severity:** low — debugging convenience, no semantic change to full runs.
- **Rollback:** remove the mode; full-run behaviour unchanged.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Execution always starts at the first step | T1 | `WorkflowExecutor.php:113` | direct read |
| Dependency resolution is available | T1 | `DependencyGraph::getEffectiveDependencies()` | direct read |
| Resuming a subset of steps is already possible | T1 | `CliRunner.php:138-170` | direct read |
| Missing dependency outputs can be detected before the request | T2 | expression resolution happens during request compilation | inferred |