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

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Expose core HAR 1.2 serialization via CLI flag so `arazzo run` can write a HAR archive of all executed requests/responses.

**Architectural Boundary:** Bounded context = CLI `run` command output + HAR export. Packages: `packages/cli` (`RunCommand` `--har-output` option), `packages/runner` (step results — draft 33 core), `packages/contracts` (`WorkflowContext`). CLI port only; core HAR serializer is draft 33. Secret redaction (draft 22) applies at serialization time.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** HAR written once after run; per-step entries; no streaming overhead.
- [ ] **Flexible:** `--har-output <path>` additive option; `HarSerializer` (draft 33 core) produces HAR 1.2; secret redaction delegated to draft 22.
- [ ] **Maintainable:** Additive CLI option; models existing `FileStateStore` JSON persistence pattern; unwritable path errors clearly.
- [ ] **Operable:** Write-after-run only; rollback by removing option (no data migration).
- [ ] **Reliable:** One entry per executed step request; deterministic HAR 1.2 shape.
- [ ] **Safe:** Read-only serialization; redaction does not affect in-memory execution values.
- [ ] **Secure:** Secret redaction (draft 22) applied before serialization; no credentials in plaintext.
- [ ] **Suitable:** Closes gap where request/response data captured per step but never exported as archive.
- [ ] **Usable:** `arazzo run doc.yaml --har-output run.har`.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `--har-output <path>` option**
- **Given** `arazzo run doc.yaml --har-output run.har`; `RunCommand` accepts `--har-output <path>` option; `HarSerializer` (draft 33 core) produces HAR 1.2 archive; `StepResult` captures request/response per step via `WorkflowContext::withStepRequest()`/`withStepResponse()`.
- **When** `php bin/console arazzo run doc.yaml --har-output run.har` executes.
- **Then** a HAR 1.2 archive is written to `run.har`; `log.version` is `"1.2"`; `creator` is `{"name": "arazzo", "version": "1.0.0"}`; `log.entries[]` contains one entry per executed step request; each entry has `startedDateTime`, `time`, `request`, `response`, `cache`, `timings`.

**Scenario 2: HAR 1.2 serializer produces valid output**
- **Given** per-step request/response records captured in `StepResult`; `HarSerializer` serializes to HAR 1.2 format.
- **When** `HarSerializer` serializes the entries.
- **Then** `log.version = "1.2"`; `creator` object is present; `entries[]` array contains one entry per step; each entry conforms to HAR 1.2 specification.

**Scenario 3: One entry per step request**
- **Given** a workflow with multiple HTTP steps (step-1, step-2, step-3); each step's request/response is captured via `WorkflowContext::withStepRequest()`/`withStepResponse()`.
- **When** `HarSerializer` generates the HAR archive.
- **Then** one `entries[]` item per executed step request (3 entries total); each entry corresponds to one step's request/response pair.

**Scenario 4: Secret redaction applied**
- **Given** a request with header `Authorization: Bearer sk-test-1234567890abcdef`; `Redactor` (draft 22 core) is applied before serialization; `HarSerializer` integrates with `Redactor`.
- **When** `HarSerializer` serializes the HAR entry including request headers.
- **Then** the `Authorization` header value in the HAR archive is `<redacted>`; the token `sk-test-1234567890abcdef` does not appear in plaintext in the HAR file; the header key `Authorization` is preserved; secret redaction does not affect in-memory execution values.

**Scenario 5: Unwritable path errors clearly**
- **Given** `--har-output /unwritable/path/run.har` where the directory `/unwritable/path/` does not exist or is not writable; `RunCommand` attempts to write the HAR file.
- **When** `php bin/console arazzo run doc.yaml --har-output /unwritable/path/run.har` executes.
- **Then** a clear CLI error is raised; `RuntimeException` or `InvalidArgumentException` is thrown with message identifying the unwritable path; no partial HAR file is left; exit code is non-zero.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `RunCommand` gains `--har-output <path>` option
- [ ] HAR 1.2 serializer (draft 33 core) produces `log.version = "1.2"`, `creator`, `entries[]`
- [ ] One entry per executed step request
- [ ] Secret redaction (draft 22) applied before serialization
- [ ] Unwritable path errors with clear message
- [ ] `RunCommandTest` case: `--har-output` produces valid HAR 1.2 with one entry per step
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Core HAR serializer (draft 33) is prerequisite; this draft is CLI port only

### 🧬 10. Source Grounding & Code Location

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

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive CLI option; new `HarSerializer` class (draft 33 core, internal).
- **Blast radius:** `packages/cli` `run`; persisted artifact content (HAR file). Secret redaction intersects with draft 22.
- **Severity:** medium — debuggability; no behaviour change by default.
- **Rollback:** remove option; no data migration.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No `--har-output` option | T1 | `RunCommand.php:45-51` | direct read |
| No HAR code anywhere | T1 | `grep -rIn -i "har\b\|http archive" packages --exclude-dir=vendor` → no matches | executed grep |
| Request/response captured per step | T1 | `StepExecutor.php:81-82,126-159`; `WorkflowContext.php:258-281` | direct read (draft 33 core) |
| Draft 33 is the core dependency | T2 | draft 33 (core HAR serializer) | cross-ref |