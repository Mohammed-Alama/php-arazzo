# Draft issue 55 — CLI: `arazzo test` workflow test runner command

- **Suggested title:** `[Feature] `arazzo test` — run Arazzo workflows as CI test cases`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #26 Testing and Adapter Parity)
- **Packages:** `cli`, `runner`, `document`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` new console command; `packages/runner` execution; `packages/document` discovery and parsing.
- **Arazzo spec version:** 1.0.x and 1.1.0 documents.
- **PHP target:** 8.4.
- **Ecosystem:** CLI entry point only; the core test engine lives in draft 46.
- **Kind / level:** `kind:feature`, `level:story`, under #26 (Testing and Adapter Parity).
- **Priority:** P1 — turns the runtime into something CI can use.

### 📝 2. Problem Statement & Intent

Draft 46 core adds a `TestRunner` engine. This draft exposes it via the CLI:

- The repository has a conformance harness, but there is no user-facing command that runs Arazzo workflows as tests. A team that authors `*.arazzo.yaml` files cannot wire them into CI without writing PHP that constructs the runner by hand.
- `arazzo run` executes one workflow and prints human text. It cannot discover documents recursively, run every workflow or a filtered subset, report per-case pass/fail in a machine-readable format, or exit non-zero when a workflow fails while still reporting every case.

Expected: `arazzo test <paths>...` discovers `.arazzo.yaml` / `.arazzo.yml` / `.arazzo.json` files, executes selected workflows as test cases, and emits TAP, JUnit XML or JSON with a deterministic exit code.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `arazzo test <paths>...` where each path is a file or directory.
- Recursive discovery of `*.arazzo.yaml`, `*.arazzo.yml`, `*.arazzo.json`.
- One test case per workflow. Support `--filter <regex>` on workflow id.
- `--format <tap|junit|json>` (default `tap`); `--json` overrides to the JSON format for consistency with draft 54.
- `--fail-fast` stops after the first failed case.
- Shared execution options: `--input`, `--header`, `--http-timeout`, `--execution-timeout`, `--parallel`, `--strict-inputs`, `--max-response-size`.
- Exit codes: `0` all passed; `1` at least one case failed; `2` suite error (unreadable document, excluded required dependency, invalid filter).
- Suite errors are reported even when `--fail-fast` is set.
- `--dry-run` is intentionally **not** supported on `test`; preview with `run --dry-run` (draft 58). A test runner that sends no requests is a false signal.

**Non-goals**

- Replacing the Pest conformance suite in `packages/core/tests`.
- A new assertion language. Pass/fail is the workflow's own `successCriteria` and terminal status.
- Remote test discovery or a daemon mode.
- Core `TestRunner` engine — that is draft 46.

**Prior art in this repository**

- `packages/core/tests/Conformance/OaiCorpusRunner.php` already discovers the OAI corpus and executes it with a deterministic transport; the command can reuse the same document discovery and runner seams.
- `#85` establishes the formatter pattern for `validate`; this draft reuses the idea for run results.
- `Application.php:25-31` — the five registered commands; `test` is absent.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Expose core `TestRunner` engine via CLI so `arazzo test` runs Arazzo workflows as CI test cases.

**Architectural Boundary:** Bounded context = CLI test command. Packages: `packages/cli` (new command), `packages/runner` (execution), `packages/document` (discovery/parsing). CLI port only; core engine is draft 46. Depends on drafts 11 (real execution) and 52 (input validation).

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** One document parse per invocation; recursive discovery O(files); each workflow executed once.
- [ ] **Flexible:** `--filter <regex>` on workflow id; `--format <tap|junit|json>`; shared execution options flow through.
- [ ] **Maintainable:** Reuses `RunnerFacade` per-workflow result shape and `OaiCorpusRunner` patterns; formatter pattern from `#85`.
- [ ] **Operable:** Deterministic exit codes (0/1/2); suite errors reported even with `--fail-fast`; machine-readable for CI.
- [ ] **Reliable:** One test case per workflow (`document#workflowId`); malformed documents produce suite errors not silent skips.
- [ ] **Safe:** `--dry-run` intentionally not supported on `test` (false signal); preview via `run --dry-run`.
- [ ] **Secure:** No authorization impact; input validation (draft 19/52) applies to `--input`.
- [ ] **Suitable:** Turns runtime into something CI can use; pass/fail is workflow's own `successCriteria`.
- [ ] **Usable:** `arazzo test <paths>...`; TAP/JUnit/JSON formats; clear per-case reporting.

---

## 8. Behavioral Specification (Given / When / Then)

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `arazzo test <dir>` discovers documents recursively**
- **Given** a directory tree (`/project/tests/`) containing `wf1.arazzo.yaml`, `subdir/wf2.arazzo.yml`, `deep/nested/wf3.arazzo.json`; each document contains at least one workflow; `TestRunner` (draft 46 core) is wired into the `test` command.
- **When** `php bin/console arazzo test /project/tests/` is invoked.
- **Then** all three documents are discovered recursively; each document's workflows are registered as test cases; `TestRunner` returns a structured result with one test case per workflow.

**Scenario 2: One test case per workflow**
- **Given** an Arazzo document (`/project/tests/wf1.arazzo.yaml`) containing two workflows: `workflowId: "build"` and `workflowId: "deploy"`; `RunnerFacade` (draft 11 core) provides per-workflow result shapes.
- **When** the test runner executes each workflow in the document.
- **Then** two test cases are created: `/project/tests/wf1.arazzo.yaml#build` and `/project/tests/wf1.arazzo.yaml#deploy`; each case reports pass/fail independently.

**Scenario 3: `--filter` selects workflow ids**
- **Given** an Arazzo document containing workflow `deploy` with `dependsOn: ["build"]`; `arazzo test /project/tests/ --filter 'deploy'` is invoked; workflow `build` is excluded by the filter.
- **When** `TestRunner` applies the filter and checks dependency resolution.
- **Then** only workflow `deploy` runs; a suite error is reported because `deploy` depends on excluded workflow `build`; `TestRunner` returns exit code 2 (suite error).

**Scenario 4: TAP output valid TAP 13**
- **Given** `arazzo test /project/tests/ --format tap` is invoked (default format); `TestRunner` has executed two workflows (one passed, one failed).
- **When** TAP 13 output is generated.
- **Then** output starts with `TAP version 13`; contains `1..2` (test count); each test case is named with `ok 1 - ...` or `not ok 2 - ...`; the format is valid TAP 13.

**Scenario 5: Exit codes follow contract**
- **Given** three workflows executed: all pass → exit 0; at least one fails → exit 1; suite error (missing document, invalid filter) → exit 2.
- **When** the test runner completes execution of all cases.
- **Then** exit code `0` when all cases passed; exit code `1` when at least one case failed; exit code `2` when a suite error occurs; suite errors are reported even when `--fail-fast` is set.

**Scenario 6: Malformed document → suite error**
- **Given** a directory containing `broken.arazzo.yaml` with invalid YAML syntax (unbalanced mapping key).
- **When** `TestRunner` discovery attempts to parse `broken.arazzo.yaml`.
- **Then** a suite error is reported (exit code 2); the document is not silently skipped; the error identifies the malformed file and the parse error.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `arazzo test <dir>` discovers `.arazzo.yaml/.yml/.json` recursively
- [ ] Each workflow = one test case `document#workflowId`
- [ ] `--filter <regex>` selects workflow ids; suite error on excluded dependency
- [ ] `--format <tap|junit|json>` (default tap); `--json` overrides to JSON
- [ ] `--fail-fast` stops after first failed case
- [ ] TAP valid TAP 13; JUnit valid with `<testcase>` per workflow; JSON validates `test-report.v1`
- [ ] Exit codes: 0 all passed, 1 at least one failed, 2 suite error
- [ ] Suite errors reported even with `--fail-fast`
- [ ] Workflow failing `successCriteria` → failing case with criterion reason
- [ ] Malformed document → suite error, not silent skip
- [ ] `--dry-run` not supported on `test`
- [ ] Fixture tests: discovery, filtering, fail-fast, each format, each exit code
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Core engine (`TestRunner`) is draft 46; this draft is CLI port only

### 🧬 10. Source Grounding & Code Location

- `packages/cli/src/Console/Application.php:25-31` — the five registered commands; `test` is absent.
- `packages/core/tests/Conformance/OaiCorpusRunner.php` — existing discovery/execution harness to reuse conceptually.
- `packages/runner/src/RunnerFacade.php:44-70` — per-workflow result shape.

<details><summary><code>Application.php:25-31</code></summary>

```php
$this->addCommands([
    new ValidateCommand(),
    new ListWorkflowsCommand(),
    new ExplainCommand(),
    new RunCommand(),
    new RenderCommand(),
]);
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** new command; additive.
- **Blast radius:** `packages/cli`; depends on drafts 11 (real execution) and 52 core (input validation) to be meaningful.
- **Severity:** medium — CI adoption depends on it.
- **Rollback:** remove the command registration; no persisted state.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| The CLI registers five commands and no `test` | T1 | `Application.php:25-31` | direct read |
| No workflow test runner exists | T1 | grep for `class .*TestCommand` in `packages/cli/src` → no matches | executed |
| The runner exposes per-workflow status and steps | T1 | `RunnerFacade.php:44-70` | direct read |
| Draft 46 is the core dependency | T2 | draft 46 (test runner engine) | cross-ref |