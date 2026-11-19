# Draft issue 30 — Multi-workflow selection and skip

- **Suggested title:** `[Feature] Select and skip multiple workflows at execution time`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `runner`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/runner` `RunnerFacade` (single-workflow entry); `WorkflowExecutor` (single-workflow execution).
- **Arazzo spec version:** independent of spec version; affects execution against multi-workflow documents.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core; port-agnostic (usable from CLI, MCP, or UI).
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P2 — running more than one workflow requires repeated execution invocations; there is no way to run a subset or skip a subset in one call.

### 📝 2. Problem Statement & Intent

`RunnerFacade::run()`/`execute()` take a single `$workflowId` and execute exactly one workflow (`RunnerFacade.php:56-72`). There is no mechanism to select a subset of workflows or to skip a subset:

- An author with workflows `build`, `test`, `deploy` must invoke execution three times, re-parsing the document and re-running preflight each time.
- No `--skip` equivalent exists at the runner level.

Expected: execution accepts a selection (workflow ids to run) and a skip set (workflow ids to exclude), runs each selected workflow once in document order, and returns per-workflow results. Skip wins over select when an id appears in both. Selection must respect `dependsOn`: running a workflow that depends on an excluded/skipped workflow is a clear error until draft 26 auto-runs dependencies.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Execution accepts a selection list and a skip list of workflow ids.
- When selection is omitted, behaviour is unchanged: the first (or default) workflow runs.
- Selected workflows execute in document order; each result is reported.
- Skip wins over select when a workflow id appears in both.
- Unknown selection/skip ids error with a clear message.
- A selected workflow whose `dependsOn` target is skipped/unselected fails with a clear error.

**Non-goals**

- Auto-running dependencies (draft 26 covers workflow-level `dependsOn` execution).
- CLI flags (repeatable `--workflow`/`--skip` on `RunCommand`) — tracked separately in draft 40.

**Prior art in this repository**

- `packages/runner/src/RunnerFacade.php:56-72` — `run()`/`execute()` single-workflow entry points to extend.
- `packages/document/src/Parser/Parser.php:153-163` — `dependsOn` parsed into `list<string>` (used for the guard).

### 📄 4. Structural Artifacts & Raw Inputs

```php
// An execution call selects which workflows to run:
$result = $runner->run($document, selection: ['build', 'deploy'], skip: ['test']);
```

Minimal document:

```yaml
arazzo: 1.1.0
info: { title: Repro, version: "1" }
sourceDescriptions:
  - name: api, url: https://example.com/openapi.yaml, type: openapi
workflows:
  - workflowId: build
    steps:
      - stepId: b
        operationId: $sourceDescriptions.api.build
  - workflowId: test
    dependsOn: [build]
    steps:
      - stepId: t
        operationId: $sourceDescriptions.api.test
  - workflowId: deploy
    steps:
      - stepId: d
        operationId: $sourceDescriptions.api.deploy
```

Observed today: `run($document, 'build')` runs exactly one workflow; no selection/skip.
Expected: `run($document, selection: ['build', 'deploy'], skip: ['test'])` runs `build` and `deploy`, skips `test`, reports per-workflow status, exit non-zero if any failed.

### 🌀 5. Runtime Execution State Context

- Command: entry point calls `RunnerFacade::run()` once per selected workflow, or a new multi-workflow entry point.
- Selection: intersect document workflow ids with selection values; subtract skip values.
- Order: preserve document order for selected workflows.
- `dependsOn` guard: if a selected workflow depends on a skipped/unselected workflow, fail fast with a clear error (until draft 26 auto-runs dependencies).

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Allow execution to select a subset of workflows (selection list) and skip a subset (skip list) in a single call, returning per-workflow results, instead of requiring repeated execution invocations.

**Architectural Boundary:** Bounded context = runner execution. Package: `packages/runner` (`RunnerFacade`, `WorkflowExecutor`). Port-agnostic; CLI port tracked in draft 40. Depends on draft 26 for dependency auto-execution (until then, skipped deps are an error).

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Single document parse per invocation; each selected workflow executed once; no redundant re-parsing or preflight.
- [ ] **Flexible:** Selection and skip lists are additive parameters; selection omitted = current behavior (first workflow); skip wins over select.
- [ ] **Maintainable:** Additive `RunnerFacade` parameters; `RunnerFacadeTest`/`WorkflowExecutorTest` gain cases; dependsOn guard until draft 26.
- [ ] **Operable:** Non-zero exit if any selected workflow failed; per-workflow results reported; rollback by removing parameters (single-workflow unchanged).
- [ ] **Reliable:** Selected workflows execute in document order; unknown selection/skip ids error with clear message; dependsOn guard prevents running workflows with skipped dependencies.
- [ ] **Safe:** No state mutation beyond normal execution; skip wins over select to prevent accidental execution; no cross-document effects.
- [ ] **Secure:** No new authorization surface; selection/skip are operational concerns.
- [ ] **Suitable:** Addresses QoL gap where authors with `build`, `test`, `deploy` workflows must invoke execution three times.
- [ ] **Usable:** Simple API `$runner->run($document, selection: ['build', 'deploy'], skip: ['test'])`.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: Selection list runs selected workflows**
- **Given** an Arazzo document containing workflows `build`, `test`, `deploy`; `RunnerFacade::run()` is called with `selection: ['build', 'deploy']`; `skip` is empty; `RunnerFacade` (draft 30 core) supports multi-workflow selection.
- **When** `RunnerFacade::run($document, null, [], selection: ['build', 'deploy'], skip: [])` executes.
- **Then** workflows `build` and `deploy` execute; workflow `test` does not; each result is reported individually; per-workflow results are aggregated.

**Scenario 2: Omitted selection = current behavior**
- **Given** an Arazzo document containing workflows `build`, `test`, `deploy`; no `selection` parameter is passed to `RunnerFacade::run()`.
- **When** `RunnerFacade::run($document, null, [])` executes (default behavior).
- **Then** the first (default) workflow (`build`) runs; behavior is unchanged from the single-workflow entry point; `RunnerFacade::run($document, 'build', [])` equivalent.

**Scenario 3: Selected workflows execute in document order**
- **Given** an Arazzo document where `build` appears before `deploy` in document order; `selection: ['deploy', 'build']` is passed (out of document order).
- **When** `RunnerFacade::run()` executes selected workflows.
- **Then** `build` executes before `deploy` (document order preserved, not selection order); `RunnerFacade` sorts selected workflows by their position in the document.

**Scenario 4: Skip wins over select**
- **Given** `selection: ['build', 'test']` and `skip: ['test']`; both `build` and `test` are in the selection list, but `test` is also in the skip list.
- **When** `RunnerFacade::run()` computes the effective selection (intersect selection with document IDs, subtract skip).
- **Then** only `build` executes; `test` is excluded even though it appears in `--workflow`; skip wins over select.

**Scenario 5: Unknown selection/skip ids error**
- **Given** `selection: ['nonexistent']` where `nonexistent` is not a declared workflowId in the document.
- **When** `RunnerFacade::run()` validates selection/skip IDs against declared workflow IDs.
- **Then** a clear error message is returned naming the unknown ID (`nonexistent`); valid workflow IDs are listed; execution does not proceed; non-zero exit.

**Scenario 6: Selected workflow with skipped dependency fails**
- **Given** workflow `test dependsOn: ["build"]`; `selection: ['test']` and `skip: ['build']`; draft 26 (dependency auto-execution) is not yet implemented.
- **When** `RunnerFacade::run()` executes workflow `test`.
- **Then** a clear error is returned: workflow `test` depends on skipped workflow `build`; execution fails fast with a clear error; `dependent` is not executed until draft 26 auto-runs dependencies.

**Scenario 7: Non-zero exit if any selected workflow failed**
- **Given** `selection: ['build', 'deploy']`; workflow `deploy` fails during execution (step failure or unmet `successCriteria`); workflow `build` succeeds.
- **When** execution completes.
- **Then** exit code is non-zero (failure); per-workflow results are reported (`build`: succeeded, `deploy`: failed); the overall run result reflects that at least one selected workflow failed.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] Execution accepts a selection list and a skip list of workflow ids
- [ ] Omitted selection = unchanged behavior (first/default workflow runs)
- [ ] Selected workflows execute in document order; each result reported
- [ ] Skip wins over select when id appears in both
- [ ] Unknown selection/skip ids error with clear message
- [ ] Selected workflow whose `dependsOn` target is skipped/unselected fails with clear error
- [ ] Non-zero exit if any selected workflow failed
- [ ] `RunnerFacadeTest`/`WorkflowExecutorTest` cases: multiple workflows, skip, unknown id, dependsOn guard
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] CLI port (repeatable `--workflow`/`--skip`) tracked separately in draft 40

### 🧬 10. Source Grounding & Code Location

- `packages/runner/src/RunnerFacade.php:56-72` — `run()`/`execute()` single-workflow entry points.
- `packages/cli/src/Console/Command/RunCommand.php:45-51` — `--workflow` single `VALUE_REQUIRED`; no `--skip` (CLI side, referenced for context only).
- `packages/document/src/Parser/Parser.php:153-163` — `dependsOn` parsed (used for the guard).

<details><summary><code>RunnerFacade.php:56-72</code></summary>

```php
    public function run(ArazzoDocument $document, string $workflowId, array $inputs = []): array
    {
        $result = $this->executor->execute($this->workflow($document, $workflowId), $document, $inputs);
        return $this->baseResult($result);
    }
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive `RunnerFacade` parameter(s); `RunnerFacade` may gain a multi-workflow convenience method (internal).
- **Blast radius:** `packages/runner` execution path; any entry point invoking execution multiple times can consolidate.
- **Severity:** medium — QoL for multi-workflow documents; no behaviour change by default.
- **Rollback:** remove selection/skip parameters; single-workflow behaviour unchanged.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `RunnerFacade` executes one workflow | T1 | `RunnerFacade.php:56-72` | direct read |
| `dependsOn` parsed into `list<string>` | T1 | `Parser.php:153-163` | direct read |
| No selection/skip parameter exists today | T1 | grep for `selection`/`skip` in `RunnerFacade` → no matches | executed grep |
| `dependsOn` guard needed for cross-workflow selection | T2 | draft 26 covers dependency execution; until then, error on skipped deps | inferred |