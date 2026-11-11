# Draft issue 13 — `arazzo test` workflow test runner

- **Suggested title:** `[Feature] `arazzo test` — run Arazzo workflows as CI test cases`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #26 Testing and Adapter Parity)
- **Packages:** `cli`, `runner`, `document`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` new console command; `packages/runner` execution; `packages/document` discovery and parsing.
- **Arazzo spec version:** 1.0.x and 1.1.0 documents.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core CLI.
- **Kind / level:** `kind:feature`, `level:story`, under #26 (Testing and Adapter Parity).
- **Priority:** P1 — turns the runtime into something CI can use.

### 📝 2. Problem Statement & Intent

The repository has a conformance harness, but there is no user-facing command that
runs Arazzo workflows as tests. A team that authors `*.arazzo.yaml` files cannot
wire them into CI without writing PHP that constructs the runner by hand.

`arazzo run` executes one workflow and prints human text. It cannot:

- discover documents recursively,
- run every workflow or a filtered subset,
- report per-case pass/fail in a machine-readable format,
- exit non-zero when a workflow fails while still reporting every case.

Expected: `arazzo test <paths>...` discovers `.arazzo.yaml` / `.arazzo.yml` /
`.arazzo.json` files, executes selected workflows as test cases, and emits TAP,
JUnit XML or JSON with a deterministic exit code.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `arazzo test <paths>...` where each path is a file or directory.
- Recursive discovery of `*.arazzo.yaml`, `*.arazzo.yml`, `*.arazzo.json`.
- One test case per workflow. Support `--filter <regex>` on workflow id.
- `--format <tap|junit|json>` (default `tap`); `--json` overrides to the JSON
  format for consistency with draft 12.
- `--fail-fast` stops after the first failed case.
- Shared execution options: `--input`, `--input-json`, `--header`, `--openapi`,
  `--http-timeout`, `--execution-timeout`, `--parallel`, `--strict-inputs`,
  `--max-response-size`.
- Exit codes: `0` all passed; `1` at least one case failed; `2` suite error
  (unreadable document, excluded required dependency, invalid filter).
- Suite errors are reported even when `--fail-fast` is set.
- `--dry-run` is intentionally **not** supported on `test`; preview with
  `run --dry-run` (draft 16). A test runner that sends no requests is a false
  signal.

**Non-goals**

- Replacing the Pest conformance suite in `packages/core/tests`.
- A new assertion language. Pass/fail is the workflow's own `successCriteria`
  and terminal status.
- Remote test discovery or a daemon mode.

**Prior art in this repository**

- `packages/core/tests/Conformance` already discovers the OAI corpus and
  executes it with a deterministic transport; the command can reuse the same
  document discovery and runner seams.
- `#85` establishes the formatter pattern for `validate`; this draft reuses the
  idea for run results.
- The Rust implementation confirms the shape: TAP/JUnit/JSON, `--filter`,
  `--fail-fast`, shared execution flags.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Provide a user-facing `arazzo test` command that runs Arazzo workflows as CI test cases, so teams authoring `*.arazzo.yaml` can wire them into CI without writing PHP to construct the runner by hand.

**Architectural Boundary:** Bounded context = CLI test command + runner execution + document discovery. Packages: `packages/cli` (new console command), `packages/runner` (execution), `packages/document` (discovery and parsing). Core test engine is draft 46; this draft is the CLI port only. Depends on drafts 11 (real execution) and 19 (input validation) to be meaningful.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** One document parse per invocation; recursive discovery is O(files); each workflow executed once; no redundant re-parsing.
- [ ] **Flexible:** `--filter <regex>` on workflow id; `--format <tap|junit|json>`; shared execution options flow through (`--input`, `--header`, `--http-timeout`, `--parallel`, `--strict-inputs`).
- [ ] **Maintainable:** Reuses `RunnerFacade` per-workflow result shape and `OaiCorpusRunner` discovery patterns; formatter pattern from `#85` (`validate`).
- [ ] **Operable:** Deterministic exit codes (0 all passed, 1 at least one failed, 2 suite error); suite errors reported even with `--fail-fast`; machine-readable output for CI.
- [ ] **Reliable:** Each workflow is one test case identified by `document#workflowId`; malformed documents produce suite errors, not silent skips; every case reported even when one fails.
- [ ] **Safe:** `--dry-run` intentionally not supported on `test` (a test runner that sends no requests is a false signal); preview via `run --dry-run` (draft 16).
- [ ] **Secure:** No authorization impact; input validation (draft 19) applies to `--input`.
- [ ] **Suitable:** Turns the runtime into something CI can use; pass/fail is the workflow's own `successCriteria` and terminal status.
- [ ] **Usable:** `arazzo test <paths>...` where each path is a file or directory; TAP/JUnit/JSON formats; clear per-case pass/fail reporting.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `arazzo test <dir>` discovers Arazzo documents recursively**
- **Given** a directory tree (`/project/tests/`) containing `wf1.arazzo.yaml`, `subdir/wf2.arazzo.yml`, and `deep/nested/wf3.arazzo.json`; each document contains at least one workflow; `TestRunner` (draft 46 core) is wired into the `test` command.
- **When** `php bin/console arazzo test /project/tests/` is invoked.
- **Then** all three documents are discovered recursively; each document's workflows are registered as test cases; `TestRunner` returns a structured result with one test case per workflow.

**Scenario 2: Each workflow becomes one test case identified by `document#workflowId`**
- **Given** an Arazzo document (`/project/tests/wf1.arazzo.yaml`) containing two workflows: `workflowId: "build"` and `workflowId: "deploy"`; `RunnerFacade` (draft 11 core) provides per-workflow result shapes.
- **When** the test runner executes each workflow in the document.
- **Then** two test cases are created: `/project/tests/wf1.arazzo.yaml#build` and `/project/tests/wf1.arazzo.yaml#deploy`; each case reports pass/fail independently.

**Scenario 3: `--filter` selects workflow ids; suite error on excluded dependency**
- **Given** an Arazzo document containing workflow `deploy` with `dependsOn: ["build"]`; `arazzo test /project/tests/ --filter 'deploy'` is invoked; workflow `build` is excluded by the filter.
- **When** `TestRunner` applies the filter and checks dependency resolution.
- **Then** only workflow `deploy` runs; a suite error is reported because `deploy` depends on excluded workflow `build`; `TestRunner` returns exit code 2 (suite error).

**Scenario 4: TAP output is valid TAP 13**
- **Given** `arazzo test /project/tests/ --format tap` is invoked (default format); `TestRunner` has executed two workflows (one passed, one failed).
- **When** TAP 13 output is generated.
- **Then** output starts with `TAP version 13`; contains `1..2` (test count); each test case is named with `ok 1 - ...` or `not ok 2 - ...`; the format is valid TAP 13.

**Scenario 5: Exit codes follow the contract**
- **Given** three workflows executed: all pass → exit 0; at least one fails → exit 1; suite error (missing document, invalid filter) → exit 2.
- **When** the test runner completes execution of all cases.
- **Then** exit code `0` when all cases passed; exit code `1` when at least one case failed; exit code `2` when a suite error occurs; suite errors are reported even when `--fail-fast` is set.

**Scenario 6: Malformed document produces a suite error, not a silent skip**
- **Given** a directory containing `broken.arazzo.yaml` with invalid YAML syntax (unbalanced mapping key).
- **When** `TestRunner` discovery attempts to parse `broken.arazzo.yaml`.
- **Then** a suite error is reported (exit code 2); the document is not silently skipped; the error identifies the malformed file and the parse error.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `arazzo test <dir>` discovers `.arazzo.yaml`, `.arazzo.yml`, `.arazzo.json` recursively
- [ ] Each workflow in each document becomes one test case identified by `document#workflowId`
- [ ] `--filter <regex>` selects workflow ids and fails with suite error on excluded dependency
- [ ] `--format <tap|junit|json>` (default `tap`); `--json` overrides to JSON format
- [ ] `--fail-fast` stops after the first failed case
- [ ] TAP output valid TAP 13; JUnit XML valid with one `<testcase>` per workflow; JSON validates against `test-report.v1` schema
- [ ] Exit codes: 0 all passed, 1 at least one failed, 2 suite error
- [ ] Suite errors reported even when `--fail-fast` is set
- [ ] Workflow failing `successCriteria` produces failing case with criterion reason
- [ ] Malformed document produces suite error, not silent skip
- [ ] `--dry-run` not supported on `test`
- [ ] Shared execution options: `--input`, `--header`, `--http-timeout`, `--execution-timeout`, `--parallel`, `--strict-inputs`, `--max-response-size`
- [ ] Fixture tests: discovery, filtering, fail-fast, each format, each exit code
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Core engine (`TestRunner`) is draft 46; this draft is the CLI port only


### 🧬 10. Source Grounding & Code Location

- `packages/cli/src/Console/Application.php:25-31` — the five registered commands; `test` is absent.
- `packages/core/tests/Conformance/OaiCorpusRunner.php` — existing discovery/execution harness to reuse conceptually.
- `packages/runner/src/RunnerFacade.php:44-70` — per-workflow result shape.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/cli/src/Console/Application.php#L25-L31"><code>Application.php:25-31</code></a> @ <code>ea8c55a</code></summary>

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
- **Blast radius:** `packages/cli`; depends on drafts 11 (real execution) and 19 (input validation) to be meaningful.
- **Severity:** medium — CI adoption depends on it.
- **Rollback:** remove the command registration; no persisted state.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| The CLI registers five commands and no `test` | T1 | `Application.php:25-31` | direct read |
| No workflow test runner exists | T1 | `grep -rIn "class .*TestCommand" packages/cli/src` → no matches | executed |
| The runner exposes per-workflow status and steps | T1 | `RunnerFacade.php:44-70` | direct read |
| A conformance discovery harness already exists | T1 | `packages/core/tests/Conformance/OaiCorpusRunner.php` | direct read |
| TAP/JUnit/JSON are sufficient CI formats | T2 | common CI consumption; confirmed by the Rust CLI's format set | inferred |
