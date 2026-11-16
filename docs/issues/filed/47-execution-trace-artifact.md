# Draft issue 47 — Execution trace artifact (`trace.v1`)

- **Suggested title:** `[Feature] Core execution trace artifact with requests, responses and timing`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `events`, `runner`, `runtime`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/events` ledger listener and event payloads; `packages/runner` execution; `packages/runtime` state.
- **Arazzo spec version:** independent of spec version.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core; port-agnostic (usable from CLI, MCP, or UI).
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P1 — prerequisite for replay (draft 48 core) and for debugging failed runs.

### 📝 2. Problem Statement & Intent

The event ledger is an audit log, not a trace. It records:

- `run.started`: workflow id, definition id, inputs
- `run.completed`: workflow id, outputs
- `step.started`: step id, attempt
- `step.executed`: step id, status code, outputs, criteria result

(`packages/events/src/Listener/LedgerEventListener.php:42-77`)

It does **not** record the request that was sent, the response body or headers, per-step wall-clock duration, transport warnings, or the ordered trace of attempts. The data exists transiently in `StepResult` (`request`, `response`, `responseBody`, `rawBody`, `responseHeaders`, `attempts`), but nothing exports it as a stable artifact.

Without a trace, a failed workflow can only be diagnosed by re-running it against live endpoints, which may be non-deterministic or destructive.

Expected: a core `trace.v1` JSON artifact and a serializer that builds it from `StepResult`/event data, so any entry point (CLI, MCP, UI) can export execution traces.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Define `trace.v1`, a versioned JSON artifact with:
  - `schemaVersion`
  - `run`: workflow id, document path, inputs, status, started/finished, duration
  - `run.steps[]` in execution order: `stepId`, `attempt`, `startedAt`, `durationMs`, `status`
  - per step: resolved `request` (method, url, headers with secrets redacted, body preview), `response` (status, headers, body preview), `outputs`, `criteria[]` with outcome and reason, `failureCategory`
  - `run.warnings[]` with stable codes
  - `run.sequentialFallbacks[]` when parallel execution (draft 50 core) runs a workflow sequentially
- A core `TraceSerializer` (or equivalent) that builds `trace.v1` from step records.
- Body capture is bounded (default 2048 bytes, max 1 MiB). Declared outputs are not truncated.
- Reuse existing events where possible; extend `StepExecutedEvent` / add a `StepTraceEvent` rather than overloading the ledger.
- Redaction is delegated to draft 22 core; until it lands, the trace marks secret headers as `<redacted>` for a fixed list (`Authorization`, `Cookie`, `Set-Cookie`, `Proxy-Authorization`) to avoid obvious leakage.

**Non-goals**

- Replay (draft 48 core).
- Replacing the event ledger.
- Distributed tracing across processes; OTel spans stay separate.
- CLI flag (`--trace <path>`) — tracked separately in draft 56.
- Recording request/response bodies without bounds.

**Prior art in this repository**

- `StepResult` already carries the request/response payload.
- `LedgerEventListener` establishes the event-to-record pattern.
- OTel spans exist for runs (`packages/runtime/src/Telemetry/OtelSetup.php`) but are not a durable artifact.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Define a `trace.v1` JSON artifact and a `TraceSerializer` that builds it from step records, so any entry point can export execution traces with requests, responses, and timing.

**Architectural Boundary:** Bounded context = execution tracing. Packages: `packages/events` (ledger listener and event payloads), `packages/runner` (execution), `packages/runtime` (state). Port-agnostic; CLI port tracked in draft 56. Depends on draft 22 for secret redaction.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Body capture bounded (default 2048 bytes, max 1 MiB); declared outputs never truncated; trace written once after run.
- [ ] **Flexible:** Versioned schema (`trace.v1`); `TraceSerializer` additive; redaction delegated to draft 22; body size cap configurable.
- [ ] **Maintainable:** `TraceSerializer` single-responsibility; reuses existing events where possible; stable error codes.
- [ ] **Operable:** Trace for failed runs written identifying failing step/criterion; rollback by dropping serializer (no state migration).
- [ ] **Reliable:** Steps in execution order with `attempt`, `startedAt`, `durationMs`, `status`; deterministic artifact.
- [ ] **Safe:** Read-only artifact generation; no state mutation; redaction at serialization boundary.
- [ ] **Secure:** Secret headers redacted (fixed list before draft 22); draft 22 extends redaction to all paths.
- [ ] **Suitable:** Prerequisite for replay (draft 48 core) and debugging failed runs; captures what the ledger omits.
- [ ] **Usable:** `trace.v1` JSON Schema published; replay test consumes trace and reproduces same outputs.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `trace.v1` JSON Schema published**
- **Given** step records from a run (stepId, method, URL, request headers, request body preview, response status, response headers, response body preview, attempts, startedAt, durationMs, status, criteria outcomes); `TraceSerializer` (draft 47 core) builds `trace.v1` artifacts from step records; `trace.v1` JSON Schema is published.
- **When** `TraceSerializer::serialize($stepRecords)` is called.
- **Then** output validates against the published `trace.v1` JSON Schema; the artifact contains `schemaVersion`, `run.workflowId`, `run.status`, `run.startedAt`, `run.finishedAt`, `run.durationMs`, `run.steps[]` with per-step timing and attempt data, `run.warnings[]`.

**Scenario 2: Steps in execution order with timing and attempt**
- **Given** a workflow with multiple steps and retries (step-1 executed once, step-2 executed 3 times due to retries); each step record has `attempt`, `startedAt`, `durationMs`, `status`.
- **When** `TraceSerializer` builds the trace artifact.
- **Then** each step record in `run.steps[]` contains `attempt` (1, 2, or 3 for retries), `startedAt` (ISO 8601 timestamp), `durationMs` (elapsed milliseconds), `status` (succeeded/failed); steps appear in execution order.

**Scenario 3: Request and response recorded per step**
- **Given** an executed step with request (method: GET, URL: `https://api.example.com/items`, headers: `Accept: application/json`, body preview) and response (status: 200, headers: `Content-Type: application/json`, body preview: `{"id":1}`).
- **When** `TraceSerializer` serializes the trace entry for this step.
- **Then** each step record contains resolved request (method, URL, headers, body preview bounded by `--trace-max-body-bytes`) and response (status, headers, bounded body preview); secret headers are redacted per draft 22 rules.

**Scenario 4: Criteria outcome and reason recorded**
- **Given** a step with success criteria (`condition: "$statusCode == 200"`, actual status 402, criterion outcome: not-met, reason: "Expected statusCode 200, got 402"); criteria are recorded per step.
- **When** `TraceSerializer` serializes the trace entry for this step.
- **Then** each criterion record contains `condition`, `outcome` (met/not-met), `reason` ("Expected statusCode 200, got 402"); the criterion outcome and reason are preserved in the trace.

**Scenario 5: Body previews bounded; outputs not truncated**
- **Given** a response body larger than `--trace-max-body-bytes` (e.g., 10 KiB body with 2 KiB limit); declared workflow outputs are separate from response bodies.
- **When** `TraceSerializer` serializes the trace entry.
- **Then** response body preview is bounded at `--trace-max-body-bytes` (2 KiB); declared workflow outputs are never truncated (full output values in `run.outputs`); body previews respect the byte bound while outputs remain complete.

**Scenario 6: Secret headers redacted**
- **Given** a request with header `Authorization: Bearer sk-test-1234567890abcdef`; `Redactor` (draft 22 core) is applied before serialization; fixed list includes `Authorization`, `Cookie`, `Set-Cookie`, `Proxy-Authorization`.
- **When** `TraceSerializer` serializes the trace entry including request headers.
- **Then** the `Authorization` header value in the trace artifact is `<redacted>`; the token `sk-test-1234567890abcdef` does not appear in plaintext in the trace file; the header key `Authorization` is preserved.

**Scenario 7: Failed run trace identifies failure**
- **Given** a failed workflow (step-2 failed at `validate-payment` with criterion `not-met`); `TraceSerializer` captures the failure.
- **When** `TraceSerializer` serializes the trace artifact for the failed run.
- **Then** the trace artifact identifies the failing step (`stepId: "validate-payment"`) and the failing criterion (`condition: "$statusCode == 200"`, outcome: not-met, reason: "Expected statusCode 200, got 402"); `ReplayTransport` (draft 48) can consume this trace and reproduce the same failure.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `trace.v1` JSON Schema published and validated against
- [ ] `TraceSerializer` builds `trace.v1` artifacts from step records
- [ ] Steps in execution order with `attempt`, `startedAt`, `durationMs`, `status`
- [ ] Each step records resolved request method/url/headers/body preview
- [ ] Each step records response status, headers, bounded body preview
- [ ] Each criterion records outcome and reason
- [ ] Body previews respect byte bound; declared outputs never truncated
- [ ] Secret headers redacted even before draft 22 lands
- [ ] Trace for failed run written identifying failing step and criterion
- [ ] Artifact stable enough for replay (draft 48 consumes it)
- [ ] `TraceSerializerTest` fixture-tests trace shape for successful and failed runs
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] CLI port (`--trace <path>` on `RunCommand`) tracked separately in draft 56

### 🧬 10. Source Grounding & Code Location

- `packages/events/src/Listener/LedgerEventListener.php:42-77` — current payload scope.
- `packages/runtime/src/State/Data/StepResult.php:21-34` — request/response fields available but unexported.
- `packages/contracts/src/State/WorkflowContext.php:255-281` — request/response are attached to the step record.
- `packages/runtime/src/Telemetry/OtelSetup.php` — OTel is not a durable trace artifact.

<details><summary><code>LedgerEventListener.php:41-79</code></summary>

```php
[$type, $payload] = match (true) {
    $event instanceof RunStartedEvent => [
        'run.started',
        ['workflowId' => $event->workflowId, 'definitionId' => $event->definitionId, 'inputs' => $event->inputs],
    ],
    // ...
    $event instanceof StepExecutedEvent => [
        'step.executed',
        ['stepId' => $event->stepId, 'statusCode' => $event->statusCode, 'outputs' => $event->outputs, 'criteriaMet' => $event->criteriaMet],
    ],
    // ...
};
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** new serializer and schema; event classes may gain fields additively.
- **Blast radius:** `packages/events`, `packages/runner`, `packages/runtime`; any entry point consuming traces.
- **Severity:** medium — diagnosability and replay depend on it.
- **Rollback:** drop the serializer; no state migration.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Ledger payloads omit request/response/timing | T1 | `LedgerEventListener.php:42-77` | direct read |
| `StepResult` carries request/response/raw body/attempts | T1 | `StepResult.php:21-34` | direct read |
| Request/response are attached to the step record | T1 | `WorkflowContext.php:258-281` | direct read |
| No trace serializer exists today | T1 | grep for `TraceSerializer` → no matches | executed grep |
| OTel spans are not persisted as an artifact | T1 | `OtelSetup.php` exports spans only | direct read |