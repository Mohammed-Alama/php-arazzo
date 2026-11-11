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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `RunCommand` gains repeatable `--workflow` (`-w`) and `--skip` (`-s`) options (`VALUE_REQUIRED`, array).
- [ ] When `--workflow` is omitted, behaviour is unchanged: the first workflow runs.
- [ ] Selected workflows execute in document order; each result is reported.
- [ ] `--skip` excludes matching workflows even if also listed in `--workflow` (skip wins).
- [ ] Unknown `--workflow`/`--skip` ids error with a clear message.
- [ ] A selected workflow whose `dependsOn` target is skipped/unselected fails with a clear error.
- [ ] Exit code: `0` if all selected workflows succeeded; non-zero if any failed.
- [ ] `RunCommandTest` gains cases: multiple workflows, skip, unknown id, dependsOn guard.
- [ ] PHPStan max and Pint pass.
- [ ] **Core multi-workflow selection** (draft 30) is a prerequisite; this draft is the CLI port only.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive CLI options; `RunnerFacade` may gain a multi-workflow method (draft 30 core).
- **Blast radius:** `packages/cli` `run`; scripts invoking `arazzo run` once per workflow can consolidate.
- **Severity:** medium — QoL for multi-workflow documents; no behaviour change by default.
- **Rollback:** remove options; single `--workflow` behaviour unchanged.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `--workflow` is single-valued; no `--skip` | T1 | `RunCommand.php:45-51` | direct read |
| `RunnerFacade` executes one workflow | T1 | `RunnerFacade.php:56-72` | direct read |
| `dependsOn` parsed into `list<string>` | T1 | `Parser.php:153-163` | direct read |
| Draft 30 is the core dependency | T2 | draft 30 (core multi-workflow selection) | cross-ref |