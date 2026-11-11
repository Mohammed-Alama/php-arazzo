# Draft issue 43 — CLI: `run --har-output` HAR 1.2 export

- **Suggested title:** `[Feature] `arazzo run --har-output` export a HAR 1.2 archive of all requests/responses`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #24 Transport Failure Handling)
- **Packages:** `cli`, `runner`, `contracts`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `RunCommand` `--har-output` option; `packages/runner` step results (draft 33 core HAR serialization); `packages/contracts` `WorkflowContext::withStepRequest`/`withStepResponse`.
- **Arazzo spec version:** independent of spec version; HAR is an output format, not a document construct.
- **PHP target:** 8.4.
- **Ecosystem:** CLI entry point only; the core HAR serialization lives in draft 33.
- **Kind / level:** `kind:feature`, `level:story`, under #24 (Transport Failure Handling).
- **Priority:** P2 — operators need a machine-readable archive of every request/response for debugging, support tickets, and replay; none exists.

### 📝 2. Problem Statement & Intent

Draft 33 adds core HAR 1.2 serialization (per-step request/response records → HAR entries). This draft exposes it via CLI flag:

- `WorkflowContext::withStepRequest()` / `withStepResponse()` store request/response records on each step (`WorkflowContext.php:258-281`).
- `StepExecutor` writes `withStepRequest` and `withStepResponse` per step (`StepExecutor.php:81-82`, `126-159`).
- No `--har-output` option on `RunCommand` (`RunCommand.php:45-51`); no HAR file write from the CLI.

Expected: `arazzo run --har-output file.har` writes a HAR 1.2 archive containing one entry per executed step request. Secret redaction (draft 22) applies to the archive at serialization time.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `RunCommand` gains `--har-output <path>` option.
- HAR 1.2 serializer produces `log.version = "1.2"`, `creator`, `entries[]`.
- One entry per executed step request.
- Secret redaction (draft 22) applied before serialization.
- Unwritable path errors with a clear message.

**Non-goals**

- Core HAR serializer — that is draft 33.
- HAR `cache` and detailed `timings`; `pageref` grouping; live HAR streaming.

**Prior art in this repository**

- `packages/cli/src/Console/Command/RunCommand.php:45-51` — no `--har-output` option.
- `packages/runner/src/Sync/StepExecutor.php:81-82` — `withStepRequest` recorded (draft 33 core).
- `packages/contracts/src/State/WorkflowContext.php:258-281` — `withStepRequest`/`withStepResponse` (draft 33 core).

### 📄 4. Structural Artifacts & Raw Inputs

```bash
arazzo run doc.arazzo.yaml --har-output run.har
```

Expected `run.har`: HAR 1.2 with one entry per executed step request (method, URL, headers, queryString, postData, response status/headers/body, timings).

### 🌀 5. Runtime Execution State Context

- Command: `arazzo run <file> --har-output <path>`.
- After the run completes, the CLI serializes per-step request/response records from the `WorkflowContext`/`ExecutionResult` via `HarSerializer` (draft 33 core).
- Secret redaction (draft 22) applied before serialization (do not redact in-memory execution values).
- File written after run completes; unwritable path errors with a clear message.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `RunCommand` gains `--har-output <path>` option.
- [ ] HAR 1.2 serializer (draft 33 core) produces `log.version = "1.2"`, `creator`, `entries[]`.
- [ ] One entry per executed step request.
- [ ] Secret redaction (draft 22) applied before serialization.
- [ ] Unwritable path errors with a clear message.
- [ ] `RunCommandTest` gains a case: `--har-output` produces valid HAR 1.2 with one entry per step.
- [ ] PHPStan max and Pint pass.
- [ ] **Core HAR serializer** (draft 33) is a prerequisite; this draft is the CLI port only.

### 🧬 7. Source Grounding & Code Location

- `packages/cli/src/Console/Command/RunCommand.php:45-51` — no `--har-output` option.
- `packages/runner/src/Sync/StepExecutor.php:81-82` — `withStepRequest` recorded (draft 33 core).
- `packages/runner/src/Sync/StepExecutor.php:126-159` — `withStepResponse` recorded (draft 33 core).
- `packages/contracts/src/State/WorkflowContext.php:258-281` — `withStepRequest`/`withStepResponse` (draft 33 core).
- `packages/runtime/src/State/FileStateStore.php:38-56` — existing JSON persistence pattern to model the file write after.

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

- **Public API impact:** additive CLI option; new `HarSerializer` class (draft 33 core, internal).
- **Blast radius:** `packages/cli` `run`; persisted artifact content (HAR file). Secret redaction intersects with draft 22.
- **Severity:** medium — debuggability; no behaviour change by default.
- **Rollback:** remove option; no data migration.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No `--har-output` option | T1 | `RunCommand.php:45-51` | direct read |
| No HAR code anywhere | T1 | `grep -rIn -i "har\b\|http archive" packages --exclude-dir=vendor` → no matches | executed grep |
| Request/response captured per step | T1 | `StepExecutor.php:81-82,126-159`; `WorkflowContext.php:258-281` | direct read (draft 33 core) |
| Draft 33 is the core dependency | T2 | draft 33 (core HAR serializer) | cross-ref |