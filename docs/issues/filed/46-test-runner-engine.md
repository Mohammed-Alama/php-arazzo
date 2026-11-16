# Draft issue 46 — Test runner engine

- **Suggested title:** `[Feature] Core test runner engine that executes Arazzo workflows as test cases`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #26 Testing and Adapter Parity)
- **Packages:** `runner`, `document`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/runner` execution; `packages/document` discovery and parsing.
- **Arazzo spec version:** 1.0.x and 1.1.0 documents.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core; port-agnostic (usable from CLI, MCP, or UI).
- **Kind / level:** `kind:feature`, `level:story`, under #26 (Testing and Adapter Parity).
- **Priority:** P1 — turns the runtime into something CI can use.

### 📝 2. Problem Statement & Intent

The repository has a conformance harness, but there is no core test-runner engine that runs Arazzo workflows as test cases. A team that authors `*.arazzo.yaml` files cannot wire them into CI without writing PHP that constructs the runner by hand. The engine must:

- discover documents recursively,
- run every workflow or a filtered subset,
- report per-case pass/fail,
- exit non-zero when a workflow fails while still reporting every case.

Expected: a core `TestRunner` that discovers `.arazzo.yaml`/`.arazzo.yml`/`.arazzo.json` files, executes selected workflows as test cases, and returns structured per-case results (pass/fail, failure reason, exit code). CLI flags (`arazzo test`, TAP/JUnit/JSON formatters) are a separate port.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `TestRunner` discovers documents from file or directory paths.
- Recursive discovery of `*.arazzo.yaml`, `*.arazzo.yml`, `*.arazzo.json`.
- One test case per workflow, identified by `document#workflowId`.
- Filter logic on workflow id (CLI port supplies the `--filter` flag).
- Shared execution options flow through: inputs, headers, timeouts, strict mode.
- Exit codes: `0` all passed; `1` at least one case failed; `2` suite error (unreadable document, excluded required dependency, invalid filter).
- Suite errors are reported even when `--fail-fast` is set.
- A workflow that fails `successCriteria` produces a failing case with the failing criterion reason.
- A malformed document produces a suite error, not a silent skip.

**Non-goals**

- CLI command (`arazzo test`) and formatters (TAP/JUnit/JSON) — tracked separately in draft 55.
- Replacing the Pest conformance suite in `packages/core/tests`.
- A new assertion language. Pass/fail is the workflow's own `successCriteria` and terminal status.
- Remote test discovery or a daemon mode.

**Prior art in this repository**

- `packages/core/tests/Conformance/OaiCorpusRunner.php` — existing discovery/execution harness to reuse conceptually.
- `packages/runner/src/RunnerFacade.php:44-70` — per-workflow result shape.
- `#85` establishes the formatter pattern for `validate`; the CLI port reuses the idea for run results.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Provide a core `TestRunner` engine that discovers Arazzo documents and executes workflows as test cases, returning structured per-case results (pass/fail, failure reason, exit code).

**Architectural Boundary:** Bounded context = test execution. Packages: `packages/runner` (execution), `packages/document` (discovery and parsing). Port-agnostic; CLI port (`arazzo test`) tracked in draft 55.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Recursive document discovery O(files); each workflow executed once; shared execution options flow through.
- [ ] **Flexible:** Filter logic on workflow id; shared options (inputs, headers, timeouts, strict mode); additive `TestRunner` class.
- [ ] **Maintainable:** Single-responsibility `TestRunner`; reuses `RunnerFacade` per-workflow result shape; `TestRunnerTest` fixture-tests all scenarios.
- [ ] **Operable:** Exit codes `0`/`1`/`2`; suite errors reported even with fail-fast; rollback by removing `TestRunner` class (no persisted state).
- [ ] **Reliable:** One test case per workflow (`document#workflowId`); malformed documents produce suite errors not silent skips; every case reported.
- [ ] **Safe:** Read-only discovery and execution; no side effects beyond normal workflow execution.
- [ ] **Secure:** No authorization impact; input validation (draft 52) applies to test inputs.
- [ ] **Suitable:** Turns the runtime into something CI can use; pass/fail is the workflow's own `successCriteria`.
- [ ] **Usable:** `TestRunner` discovers from file or directory paths; filter by workflow id regex.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: TestRunner discovers documents recursively**
- **Given** file or directory paths containing `.arazzo.yaml/.yml/.json` (e.g., `/project/tests/wf1.yaml`, `/project/tests/sub/wf2.yml`); `TestRunner` (draft 46 core) discovers documents from file or directory paths; recursive discovery is enabled.
- **When** `TestRunner::discoverAndRun(['/project/tests'])` executes.
- **Then** documents are discovered recursively; all `.arazzo.yaml`, `.arazzo.yml`, `.arazzo.json` files are found; each document's workflows are registered as test cases.

**Scenario 2: One test case per workflow**
- **Given** a document (`/project/tests/wf1.yaml`) containing multiple workflows (`build`, `deploy`); each workflow is registered as a test case identified by `document#workflowId`.
- **When** `TestRunner` executes each workflow in the document.
- **Then** two test cases are created: `/project/tests/wf1.yaml#build` and `/project/tests/wf1.yaml#deploy`; each case reports pass/fail independently with structured results.

**Scenario 3: Filter selects workflow ids**
- **Given** `--filter <regex>` (e.g., `--filter '^build'`) and a selected workflow that depends on an excluded workflow (e.g., `deploy dependsOn: ["build"]` but `build` is excluded by filter); `TestRunner` validates dependency resolution.
- **When** `--filter` is applied to select workflow ids.
- **Then** only matching workflows run (`build` runs, `deploy` is excluded); a suite error is reported for the excluded dependency (`deploy` depends on excluded `build`); exit code is 2 (suite error).

**Scenario 4: Structured per-case results**
- **Given** workflows with pass/fail outcomes; `TestRunner` executes each workflow and captures results.
- **When** `TestRunner` completes execution of all cases.
- **Then** results include pass/fail, failure reason, step outputs, and exit code for each case; structured per-case results are returned; failing cases include the failing criterion reason.

**Scenario 5: Exit codes follow contract**
- **Given** all passed → exit 0; at least one failed → exit 1; suite error → exit 2; `TestRunner` tracks execution outcomes.
- **When** `TestRunner` completes execution of all cases.
- **Then** exit code matches contract: `0` when all cases passed, `1` when at least one case failed, `2` when a suite error occurs (malformed document, invalid filter, excluded dependency).

**Scenario 6: Suite errors reported even with fail-fast**
- **Given** `--fail-fast` is set and a malformed document exists in the discovery paths; `TestRunner` processes documents even after failures.
- **When** `TestRunner` runs with `--fail-fast`.
- **Then** suite error is reported (not a silent skip); the malformed document produces exit code 2; all errors are collected even with `--fail-fast`.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `TestRunner` discovers `.arazzo.yaml`, `.arazzo.yml`, `.arazzo.json` recursively from file or directory paths
- [ ] Each workflow in each document becomes one test case identified by `document#workflowId`
- [ ] Filter logic selects workflow ids; suite error when selected workflow depends on excluded workflow
- [ ] Structured per-case results: pass/fail, failure reason, step outputs, exit code
- [ ] Workflow failing `successCriteria` produces failing case with criterion reason
- [ ] Malformed document produces suite error, not silent skip
- [ ] Exit codes: `0` all passed, `1` at least one failed, `2` suite error
- [ ] Suite errors reported even when fail-fast is set
- [ ] `TestRunnerTest` fixture-tests discovery, filtering, fail-fast, malformed documents, each exit code
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] CLI port (`arazzo test` with TAP/JUnit/JSON formatters) tracked separately in draft 55

### 🧬 10. Source Grounding & Code Location

- `packages/core/tests/Conformance/OaiCorpusRunner.php` — existing discovery/execution harness to reuse conceptually.
- `packages/runner/src/RunnerFacade.php:44-70` — per-workflow result shape.
- `packages/document/src/Parser/Parser.php` — document parsing used by discovery.

<details><summary><code>RunnerFacade.php:44-70</code></summary>

```php
    private function baseResult(ExecutionResult $result): array
    {
        return [
            'workflowId' => $result->workflowId,
            'status' => $result->status,
            'outputs' => $result->outputs,
            'stepsSpent' => $result->stepsSpent,
            'workflowCallStack' => $result->workflowCallStack,
            'steps' => $result->steps,
        ];
    }
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive `TestRunner` class; no public contract break.
- **Blast radius:** `packages/runner`, `packages/document`; any entry point (CLI, MCP, UI) that runs workflows as tests.
- **Severity:** medium — CI adoption depends on a test runner.
- **Rollback:** remove the `TestRunner` class; no persisted state.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No core `TestRunner` exists | T1 | grep for `TestRunner` in `packages/runner/src` → no matches | executed grep |
| Conformance discovery harness exists | T1 | `packages/core/tests/Conformance/OaiCorpusRunner.php` | direct read |
| `RunnerFacade` exposes per-workflow result | T1 | `RunnerFacade.php:44-70` | direct read |
| CLI has no `test` command | T1 | `Application.php:25-31` registers five commands, no `test` | direct read |