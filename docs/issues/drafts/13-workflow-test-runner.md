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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `arazzo test <dir>` discovers `.arazzo.yaml`, `.arazzo.yml` and `.arazzo.json` recursively.
- [ ] Each workflow in each document becomes one test case identified by `document#workflowId`.
- [ ] `--filter` selects workflow ids and fails with a suite error when a selected workflow depends on an excluded workflow.
- [ ] TAP output is valid TAP 13 and names each case.
- [ ] JUnit XML is valid and contains one `<testcase>` per workflow with `<failure>` on failure.
- [ ] JSON output validates against a published `test-report.v1` schema.
- [ ] Exit codes follow the contract above; `0` only when every case passed.
- [ ] A workflow that fails `successCriteria` produces a failing case with the failing criterion reason.
- [ ] A malformed document produces a suite error, not a silent skip.
- [ ] Fixture tests cover discovery, filtering, fail-fast, each format, and each exit code.
- [ ] PHPStan max and Pint pass.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** new command; additive.
- **Blast radius:** `packages/cli`; depends on drafts 11 (real execution) and 19 (input validation) to be meaningful.
- **Severity:** medium — CI adoption depends on it.
- **Rollback:** remove the command registration; no persisted state.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| The CLI registers five commands and no `test` | T1 | `Application.php:25-31` | direct read |
| No workflow test runner exists | T1 | `grep -rIn "class .*TestCommand" packages/cli/src` → no matches | executed |
| The runner exposes per-workflow status and steps | T1 | `RunnerFacade.php:44-70` | direct read |
| A conformance discovery harness already exists | T1 | `packages/core/tests/Conformance/OaiCorpusRunner.php` | direct read |
| TAP/JUnit/JSON are sufficient CI formats | T2 | common CI consumption; confirmed by the Rust CLI's format set | inferred |
