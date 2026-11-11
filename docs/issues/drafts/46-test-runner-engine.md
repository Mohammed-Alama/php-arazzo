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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `TestRunner` discovers `.arazzo.yaml`, `.arazzo.yml` and `.arazzo.json` recursively from file or directory paths.
- [ ] Each workflow in each document becomes one test case identified by `document#workflowId`.
- [ ] Filter logic selects workflow ids and reports a suite error when a selected workflow depends on an excluded workflow.
- [ ] Structured per-case results include pass/fail, failure reason, step outputs and exit code.
- [ ] A workflow that fails `successCriteria` produces a failing case with the failing criterion reason.
- [ ] A malformed document produces a suite error, not a silent skip.
- [ ] Exit codes follow the contract: `0` all passed, `1` at least one failed, `2` suite error.
- [ ] Suite errors are reported even when fail-fast is set.
- [ ] `TestRunnerTest` fixture-tests discovery, filtering, fail-fast, malformed documents, and each exit code.
- [ ] PHPStan max and Pint pass.
- [ ] **CLI port** (`arazzo test` command with TAP/JUnit/JSON formatters) is tracked separately in draft 55.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive `TestRunner` class; no public contract break.
- **Blast radius:** `packages/runner`, `packages/document`; any entry point (CLI, MCP, UI) that runs workflows as tests.
- **Severity:** medium — CI adoption depends on a test runner.
- **Rollback:** remove the `TestRunner` class; no persisted state.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No core `TestRunner` exists | T1 | grep for `TestRunner` in `packages/runner/src` → no matches | executed grep |
| Conformance discovery harness exists | T1 | `packages/core/tests/Conformance/OaiCorpusRunner.php` | direct read |
| `RunnerFacade` exposes per-workflow result | T1 | `RunnerFacade.php:44-70` | direct read |
| CLI has no `test` command | T1 | `Application.php:25-31` registers five commands, no `test` | direct read |