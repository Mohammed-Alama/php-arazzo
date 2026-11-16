# Draft issue 56 — CLI: `run --trace` execution trace export

- **Suggested title:** `[Feature] `arazzo run --trace` export a versioned execution trace artifact`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `cli`, `runner`, `runtime`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `RunCommand` `--trace` option; `packages/runner` step results; `packages/runtime` trace artifact (draft 47 core).
- **Arazzo spec version:** independent of spec version.
- **PHP target:** 8.4.
- **Ecosystem:** CLI entry point only; the core trace serializer lives in draft 47.
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P1 — prerequisite for replay (draft 57) and for debugging failed runs.

### 📝 2. Problem Statement & Intent

Draft 47 core adds a `TraceSerializer` that builds `trace.v1` artifacts from step records. This draft exposes it via the CLI:

- The event ledger is an audit log, not a trace. It does not record the request that was sent, the response body or headers, per-step wall-clock duration, transport warnings, or the ordered trace of attempts.
- `StepResult` (`request`, `response`, `responseBody`, `rawBody`, `responseHeaders`, `attempts`) holds the data transiently, but nothing exports it as a stable artifact from the CLI.

Expected: `arazzo run --trace <path>` writes a `trace.v1` artifact after the run, including failed runs. Secret redaction (draft 22) applies to the trace at serialization time.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `--trace <path>` writes a `trace.v1` artifact after the run, including failed runs.
- The artifact validates against a published JSON Schema and includes `schemaVersion`.
- Steps appear in execution order with `attempt`, `startedAt`, `durationMs` and `status`.
- Each step records the resolved request method/url/headers/body preview.
- Each step records the response status, headers and bounded body preview.
- Each criterion records its outcome and reason.
- Body previews respect `--trace-max-body-bytes` (default 2048, max 1 MiB). Declared outputs are not truncated.
- Secret headers are redacted even before draft 22 lands (fixed list: `Authorization`, `Cookie`, `Set-Cookie`, `Proxy-Authorization`).
- Core trace serializer — that is draft 47.

**Non-goals**

- Replay (draft 57).
- Replacing the event ledger.
- Distributed tracing across processes; OTel spans stay separate.
- Recording request/response bodies without bounds.

**Prior art in this repository**

- `StepResult` already carries the request/response payload.
- `LedgerEventListener` establishes the event-to-record pattern.
- OTel spans exist for runs (`packages/runtime/src/Telemetry/OtelSetup.php`) but are not a durable artifact.
- Draft 47 core: `TraceSerializer` and `trace.v1` JSON Schema.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Expose core `trace.v1` serialization via CLI flag so `arazzo run --trace` exports execution traces.

**Architectural Boundary:** Bounded context = CLI `run` command trace export. Packages: `packages/cli` (`RunCommand` `--trace` option), `packages/runner` (step results), `packages/runtime` (trace artifact — draft 47 core). CLI port only; core serializer is draft 47. Depends on draft 22 for secret redaction.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Trace written once after run; per-step entries; body previews bounded.
- [ ] **Flexible:** `--trace <path>` additive option; `--trace-max-body-bytes` configurable; `TraceSerializer` (draft 47 core) builds artifact.
- [ ] **Maintainable:** Additive CLI option; models existing event-to-record pattern; secret redaction delegated to draft 22.
- [ ] **Operable:** Trace written for successful and failed runs; rollback by removing option (no data migration).
- [ ] **Reliable:** Steps in execution order with `attempt`, `startedAt`, `durationMs`, `status`; deterministic artifact.
- [ ] **Safe:** Read-only artifact generation; redaction at serialization boundary; no effect on in-memory values.
- [ ] **Secure:** Secret headers redacted before serialization (draft 22 rules).
- [ ] **Suitable:** Prerequisite for replay (draft 57) and debugging failed runs.
- [ ] **Usable:** `--trace <path>` on `RunCommand`; `--trace-max-body-bytes` controls preview size.

---

## 8. Behavioral Specification (Given / When / Then)

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `--trace <path>` writes a `trace.v1` artifact**
- **Given** an Arazzo 1.1.0 document (`doc.arazzo`) containing workflow index 0 with two HTTP steps (`step-1` GET `/items`, `step-2` POST `/orders`); `arazzo run doc.arazzo --trace trace.json` is invoked; `TraceSerializer` (draft 47 core) is wired into `RunCommand`; the trace body preview bound is 2048 bytes.
- **When** the run completes successfully and `--trace trace.json` writes the artifact.
- **Then** a `trace.v1` artifact is written to `trace.json`; the artifact validates against the published `trace.v1` JSON Schema; `schemaVersion` is present; `run.workflowId` matches the executed workflow; `run.steps[]` contains two entries in execution order; each step records `attempt`, `startedAt`, `durationMs`, `status`, resolved request (method, URL, headers, body preview), and response (status, headers, bounded body preview).

**Scenario 2: Trace for successful and failed runs**
- **Given** an Arazzo document containing workflow index 0 where step index 1 (`stepId: "validate-payment"`) fails with `successCriteria` evaluating to `false` (criterion: `$statusCode == 200`, actual status 402); `arazzo run doc.arazzo --trace trace.json` is invoked.
- **When** the run fails and `--trace trace.json` writes the artifact.
- **Then** the artifact identifies the failing step (`stepId: "validate-payment"`); `run.steps[1].status` is `"failed"`; `run.steps[1].failureCategory` is set; the criterion outcome is `"not-met"` with reason `"Expected statusCode 200, got 402"`; `TraceSerializerTest` can consume this trace and reproduce the same failure.

**Scenario 3: Steps in execution order with timing/status**
- **Given** a workflow with three steps where step 2 retries twice (attempts 1, 2, 3) before succeeding; step 3 depends on step 2; `--trace trace.json` is invoked.
- **When** the trace is generated after execution.
- **Then** `run.steps[]` contains entries in execution order (step-1, step-2 attempt 1, step-2 attempt 2, step-2 attempt 3, step-3); each step records `attempt` number, `startedAt` timestamp, `durationMs` elapsed, and `status`; step-2 has `attempts: 3`.

**Scenario 4: Request/response recorded per step**
- **Given** an executed step with request (method: GET, URL: `https://api.example.com/items`, headers: `Accept: application/json`, body preview) and response (status: 200, headers: `Content-Type: application/json`, body preview: `{"id":1}`).
- **When** `TraceSerializer` serializes the trace entry for this step.
- **Then** each step record contains resolved request (method, URL, headers, body preview bounded by `--trace-max-body-bytes`) and response (status, headers, bounded body preview); secret headers are redacted per draft 22 rules.

**Scenario 5: Body previews bounded; outputs not truncated**
- **Given** an HTTP response with a 10 KiB body; `--trace-max-body-bytes` is set to 2048 (default); declared workflow outputs are 500 bytes.
- **When** `TraceSerializer` serializes the trace entry.
- **Then** the response body preview is truncated at 2048 bytes; `run.steps[].response.bodyPreview` does not exceed 2048 bytes; declared workflow outputs are never truncated and appear in full in `run.outputs`.

**Scenario 6: Secret headers redacted**
- **Given** a request with header `Authorization: Bearer sk-test-1234567890abcdef`; `Redactor` (draft 22 core) is applied before serialization; fixed list includes `Authorization`, `Cookie`, `Set-Cookie`, `Proxy-Authorization`.
- **When** `TraceSerializer` serializes the trace entry including request headers.
- **Then** the `Authorization` header value in the trace artifact is `<redacted>`; the token `sk-test-1234567890abcdef` does not appear in plaintext in the trace file; the header key `Authorization` is preserved.

**Scenario 7: Failed run trace identifies failure**
- **Given** a failed workflow (step-2 failed at `validate-payment` with criterion `not-met`); `TraceSerializer` captures the failure.
- **When** `TraceSerializer` serializes the trace artifact for the failed run.
- **Then** the trace artifact identifies the failing step (`stepId: "validate-payment"`) and the failing criterion (`condition: "$statusCode == 200"`, outcome: not-met, reason: "Expected statusCode 200, got 402"); `ReplayTransport` (draft 57) can consume this trace and reproduce the same failure.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `RunCommand` gains `--trace <path>` option
- [ ] `TraceSerializer` (draft 47 core) produces `trace.v1` artifacts for successful and failed runs
- [ ] Steps in execution order with `attempt`, `startedAt`, `durationMs`, `status`
- [ ] Each step records request method/url/headers/body preview
- [ ] Each step records response status, headers, bounded body preview
- [ ] Each criterion records outcome and reason
- [ ] Body previews respect `--trace-max-body-bytes`; declared outputs not truncated
- [ ] Secret headers redacted before serialization
- [ ] Trace for failed run written identifying failing step and criterion
- [ ] `RunCommandTest` fixture-tests trace output for successful and failed run
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Core serializer (`trace.v1`) is draft 47; this draft is CLI port only

### 🧬 10. Source Grounding & Code Location

- `packages/cli/src/Console/Command/RunCommand.php:45-51` — no `--trace` option.
- `packages/events/src/Listener/LedgerEventListener.php:42-77` — current payload scope.
- `packages/runtime/src/State/Data/StepResult.php:21-34` — request/response fields available but unexported.
- `packages/contracts/src/State/WorkflowContext.php:255-281` — request/response are attached to the step record.
- Draft 47 core: `TraceSerializer` and `trace.v1` JSON Schema.

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

- **Public API impact:** additive CLI option; new trace artifact (draft 47 core).
- **Blast radius:** `packages/cli` `run`; persisted artifact content (trace file). Secret redaction intersects with draft 22.
- **Severity:** medium — diagnosability and replay depend on it.
- **Rollback:** remove option; no data migration.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No `--trace` option | T1 | `RunCommand.php:45-51` | direct read |
| Ledger payloads omit request/response/timing | T1 | `LedgerEventListener.php:42-77` | direct read |
| `StepResult` carries request/response/raw body/attempts | T1 | `StepResult.php:21-34` | direct read |
| Draft 47 is the core dependency | T2 | draft 47 (trace serializer) | cross-ref |