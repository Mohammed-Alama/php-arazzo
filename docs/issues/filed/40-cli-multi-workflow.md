# Draft issue 40 — CLI: repeatable `--workflow` and `--skip`

- **Suggested title:** `[Feature] `arazzo run` select and skip multiple workflows`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `cli`, `runner`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `RunCommand` options; `packages/runner` `RunnerFacade` (draft 30 core multi-workflow selection).
- **Arazzo spec version:** independent of spec version; affects `arazzo run` against multi-workflow documents.
- **PHP target:** 8.4.
- **Ecosystem:** CLI entry point only; the core selection/skip logic lives in draft 30.
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P2 — running more than one workflow requires repeated `arazzo run` invocations; there is no way to run a subset or skip a subset in one command.

### 📝 2. Problem Statement & Intent

Draft 30 adds core multi-workflow selection/skip to `RunnerFacade`. This draft exposes it via CLI flags:

- `RunCommand` accepts a single `--workflow` (`VALUE_REQUIRED`, not an array) and there is no `--skip` (`RunCommand.php:45-51`).
- An author with workflows `build`, `test`, `deploy` must invoke the CLI three times.

Expected: `arazzo run --workflow <id>` (repeatable) runs the selected workflows; `--skip <id>` (repeatable) excludes workflows. Skip wins over select. When neither is given, the first workflow runs (current behaviour). Selection must respect `dependsOn`: running a workflow that depends on an excluded/skipped workflow is a clear error until draft 26 auto-runs dependencies.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `RunCommand` gains repeatable `--workflow` (`-w`) and `--skip` (`-s`) options (`VALUE_REQUIRED`, array).
- When `--workflow` is omitted, behaviour is unchanged: the first workflow runs.
- Selected workflows execute in document order; each result is reported.
- Skip wins over select; unknown ids error with a clear message.
- A selected workflow whose `dependsOn` target is skipped/unselected fails with a clear error.
- Exit code: `0` if all selected workflows succeeded; non-zero if any failed.

**Non-goals**

- Core multi-workflow selection logic — that is draft 30.
- Machine-readable multi-result report — draft 12.

**Prior art in this repository**

- `packages/cli/src/Console/Command/RunCommand.php:45-51` — `--workflow` single `VALUE_REQUIRED`; no `--skip`.
- `packages/runner/src/RunnerFacade.php:56-72` — `run()`/`execute()` single-workflow entry points (draft 30 core).

### 📄 4. Structural Artifacts & Raw Inputs

```bash
arazzo run doc.arazzo.yaml --workflow build --workflow deploy --skip test
```

### 🌀 5. Runtime Execution State Context

- Command: `arazzo run <file> [--workflow id]... [--skip id]...`.
- Selection: intersect document workflow ids with `--workflow` values; subtract `--skip` values.
- Order: preserve document order for selected workflows.
- `dependsOn` guard: if a selected workflow depends on a skipped/unselected workflow, fail fast with a clear error (until draft 26 auto-runs dependencies).
- Each selected workflow executed via `RunnerFacade::run()`; results aggregated; exit non-zero if any failed.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Expose core multi-workflow selection/skip via CLI flags so `arazzo run` can run a subset of workflows in one command instead of repeated invocations.

**Architectural Boundary:** Bounded context = CLI `run` command options. Packages: `packages/cli` (`RunCommand` options), `packages/runner` (`RunnerFacade` — draft 30 core). CLI port only; core selection/skip logic is draft 30.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** CLI flag parsing is O(flags); workflow selection happens before execution; each selected workflow executed once.
- [ ] **Flexible:** Repeatable `--workflow` (`-w`) and `--skip` (`-s`); skip wins over select; omitted `--workflow` = current behavior (first workflow).
- [ ] **Maintainable:** Additive CLI options; `RunCommandTest` gains cases; `dependsOn` guard until draft 26 auto-runs dependencies.
- [ ] **Operable:** Exit code `0` if all succeeded, non-zero if any failed; per-workflow results reported; rollback by removing options.
- [ ] **Reliable:** Selected workflows execute in document order; unknown ids error with clear message; dependsOn guard prevents running workflows with skipped dependencies.
- [ ] **Safe:** CLI-only wiring; execution logic unchanged; no state mutation beyond normal execution.
- [ ] **Secure:** No authorization/ACL impact; selection/skip are operational concerns.
- [ ] **Suitable:** Closes QoL gap where authors with `build`, `test`, `deploy` must invoke CLI three times.
- [ ] **Usable:** `arazzo run doc.yaml --workflow build --workflow deploy --skip test`.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: Repeatable `--workflow` runs selected workflows**
- **Given** `arazzo run doc.yaml --workflow build --workflow deploy --skip test`; `RunCommand` (draft 40 core) accepts repeatable `--workflow` (`-w`) and `--skip` (`-s`) options; `RunnerFacade::run()` (draft 30 core) supports multi-workflow selection.
- **When** `php bin/console arazzo run doc.yaml --workflow build --workflow deploy --skip test` executes.
- **Then** `build` and `deploy` execute; `test` is skipped; each result is reported individually; per-workflow results are aggregated.

**Scenario 2: Omitted `--workflow` = current behavior**
- **Given** no `--workflow` flag is passed; `arazzo run doc.yaml` is invoked without `--workflow`.
- **When** `RunCommand` executes without `--workflow`.
- **Then** the first workflow runs (current behavior unchanged); `RunnerFacade::run($document, 'first-workflow', [])` equivalent.

**Scenario 3: Selected workflows in document order**
- **Given** `--workflow deploy --workflow build` where `build` appears before `deploy` in the document; `RunnerFacade` sorts selected workflows by document position.
- **When** `php bin/console arazzo run doc.yaml --workflow deploy --workflow build` executes.
- **Then** `build` executes before `deploy` (document order preserved, not selection order).

**Scenario 4: Skip wins over select**
- **Given** `--workflow build --workflow test --skip test`; both `build` and `test` are in selection, but `test` is also in skip.
- **When** `RunCommand` computes effective selection (intersect selection with document IDs, subtract skip).
- **Then** only `build` executes; `test` is excluded even though it appears in `--workflow`; skip wins over select.

**Scenario 5: Unknown ids error**
- **Given** `--workflow nonexistent` where `nonexistent` is not a declared workflowId in the document.
- **When** `RunCommand` validates workflow IDs against declared workflows.
- **Then** a clear CLI error is returned naming the unknown ID (`nonexistent`); valid workflow IDs are listed; execution does not proceed; non-zero exit code.

**Scenario 6: DependsOn guard**
- **Given** `test dependsOn: [build]`; `--workflow test --skip build`; draft 26 (dependency auto-execution) is not yet implemented.
- **When** `RunCommand` executes workflow `test` after skipping `build`.
- **Then** a clear error is returned: workflow `test` depends on skipped workflow `build`; execution fails fast with a clear error; `dependent` is not executed until draft 26 auto-runs dependencies.

**Scenario 7: Exit code**
- **Given** `selection: ['build', 'deploy']`; workflow `deploy` fails during execution; workflow `build` succeeds.
- **When** `arazzo run doc.yaml --workflow build --workflow deploy` completes.
- **Then** exit code is non-zero (failure); per-workflow results are reported (`build`: succeeded, `deploy`: failed); the overall run result reflects that at least one selected workflow failed.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `RunCommand` gains repeatable `--workflow` (`-w`) and `--skip` (`-s`) options (`VALUE_REQUIRED`, array)
- [ ] Omitted `--workflow` = unchanged behavior (first workflow runs)
- [ ] Selected workflows execute in document order; each result reported
- [ ] `--skip` excludes matching workflows even if also in `--workflow` (skip wins)
- [ ] Unknown `--workflow`/`--skip` ids error with clear message
- [ ] Selected workflow whose `dependsOn` target is skipped/unselected fails with clear error
- [ ] Exit code `0` if all succeeded; non-zero if any failed
- [ ] `RunCommandTest` cases: multiple workflows, skip, unknown id, dependsOn guard
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Core multi-workflow selection (draft 30) is prerequisite; this draft is CLI port only

### 🧬 10. Source Grounding & Code Location

- `packages/cli/src/Console/Command/RunCommand.php:45-51` — `--workflow` single `VALUE_REQUIRED`; no `--skip`.
- `packages/cli/src/Console/Command/RunCommand.php:59-66` — `findWorkflow()` returns a single workflow or `null`.
- `packages/cli/src/Console/Command/RunCommand.php:121-126` — `RunnerFacade::execute()` called once for one workflow.
- `packages/runner/src/RunnerFacade.php:56-72` — `run()`/`execute()` single-workflow entry points (draft 30 core).

<details><summary><code>RunCommand.php:45-51</code></summary>

```php
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to an Arazzo YAML/JSON document')
            ->addOption('workflow', 'w', InputOption::VALUE_REQUIRED, 'workflowId to run (defaults to the first workflow)')
            ->addOption('input', 'i', InputOption::VALUE_REQUIRED, 'Workflow inputs as inline JSON or @file.json');
    }
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive CLI options; `RunnerFacade` may gain a multi-workflow method (draft 30 core).
- **Blast radius:** `packages/cli` `run`; scripts invoking `arazzo run` once per workflow can consolidate.
- **Severity:** medium — QoL for multi-workflow documents; no behaviour change by default.
- **Rollback:** remove options; single `--workflow` behaviour unchanged.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `--workflow` is single-valued; no `--skip` | T1 | `RunCommand.php:45-51` | direct read |
| `RunnerFacade` executes one workflow | T1 | `RunnerFacade.php:56-72` | direct read |
| `dependsOn` parsed into `list<string>` | T1 | `Parser.php:153-163` | direct read |
| Draft 30 is the core dependency | T2 | draft 30 (core multi-workflow selection) | cross-ref |