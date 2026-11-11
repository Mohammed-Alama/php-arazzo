# Draft issue 14 — Execution trace artifacts

- **Suggested title:** `[Feature] Emit a versioned execution trace with requests, responses and timing`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `events`, `runner`, `runtime`, `cli`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/events` ledger listener and event payloads; `packages/runner` execution; `packages/runtime` state; `packages/cli` export flag.
- **Arazzo spec version:** independent of spec version.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core plus CLI export.
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P1 — prerequisite for replay (draft 15) and for debugging failed runs.

### 📝 2. Problem Statement & Intent

The event ledger is an audit log, not a trace. It records:

- `run.started`: workflow id, definition id, inputs
- `run.completed`: workflow id, outputs
- `step.started`: step id, attempt
- `step.executed`: step id, status code, outputs, criteria result

(`packages/events/src/Listener/LedgerEventListener.php:42-77`)

It does **not** record the request that was sent, the response body or headers,
per-step wall-clock duration, transport warnings, or the ordered trace of
attempts. The data exists transiently in `StepResult` (`request`, `response`,
`responseBody`, `rawBody`, `responseHeaders`, `attempts`), but nothing exports it
as a stable artifact.

Without a trace, a failed workflow can only be diagnosed by re-running it
against live endpoints, which may be non-deterministic or destructive.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Define `trace.v1`, a versioned JSON artifact with:
  - `schemaVersion`
  - `run`: workflow id, document path, inputs, status, started/finished, duration
  - `run.steps[]` in execution order: `stepId`, `attempt`, `startedAt`, `durationMs`, `status`
  - per step: resolved `request` (method, url, headers with secrets redacted, body preview), `response` (status, headers, body preview), `outputs`, `criteria[]` with outcome and reason, `failureCategory`
  - `run.warnings[]` with stable codes
  - `run.sequentialFallbacks[]` when parallel execution (draft 17) runs a workflow sequentially
- Add `arazzo run --trace <path>`; write atomically at the end of the run, including failed runs.
- Body capture is bounded by `--trace-max-body-bytes` (default 2048, max 1 MiB). Declared outputs are not truncated.
- Reuse existing events where possible; extend `StepExecutedEvent` / add a
  `StepTraceEvent` rather than overloading the ledger.
- Redaction is delegated to draft 22; until it lands, the trace marks secret
  headers as `<redacted>` for a fixed list (`Authorization`, `Cookie`,
  `Set-Cookie`, `Proxy-Authorization`) to avoid obvious leakage.

**Non-goals**

- Replay (draft 15).
- Replacing the event ledger.
- Distributed tracing across processes; OTel spans stay separate.
- Recording request/response bodies without bounds.

**Prior art in this repository**

- `StepResult` already carries the request/response payload.
- `LedgerEventListener` establishes the event-to-record pattern.
- OTel spans exist for runs (`packages/runtime/src/Telemetry/OtelSetup.php`) but
  are not a durable artifact.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Emit a versioned execution trace artifact (`trace.v1`) containing requests, responses, and timing per step, so failed runs can be diagnosed offline and traces can feed deterministic replay.

**Architectural Boundary:** Bounded context = execution tracing. Packages: `packages/events` (ledger listener and event payloads), `packages/runner` (execution), `packages/runtime` (state), `packages/cli` (export flag). Core trace serializer is draft 47; this draft is the CLI/export port. Depends on draft 22 for secret redaction.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Body previews bounded by byte limit; trace written once after run; no per-step streaming overhead.
- [ ] **Flexible:** `TraceSerializer` is additive; versioned schema (`trace.v1`); secret redaction delegated to draft 22; body size cap configurable.
- [ ] **Maintainable:** Single-responsibility serializer; stable error codes; event classes gain fields additively; logged via `ErrorCollector`.
- [ ] **Operable:** `--trace <path>` writes artifact after run (including failed runs); no new deployment or telemetry; rollback is removing the flag.
- [ ] **Reliable:** Deterministic trace shape; steps in execution order; failed runs still produce traces identifying the failing step and criterion.
- [ ] **Safe:** Read-only artifact generation; no state mutation; redaction (draft 22) applied at serialization boundary.
- [ ] **Secure:** Secret headers redacted (fixed list: `Authorization`, `Cookie`, `Set-Cookie`, `Proxy-Authorization`); draft 22 extends redaction.
- [ ] **Suitable:** Prerequisite for replay (draft 15) and debugging failed runs; captures request/response/timing the ledger omits.
- [ ] **Usable:** `--trace <path>` flag on `RunCommand`; `--trace-max-body-bytes` controls preview size; declared outputs never truncated.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `--trace trace.json` writes a `trace.v1` artifact for a successful run**
- **Given** an Arazzo 1.1.0 document (`doc.arazzo`) containing workflow index 0 with two HTTP steps (`step-1` GET `/items`, `step-2` POST `/orders`); `arazzo run doc.arazzo --trace trace.json` is invoked; `TraceSerializer` (draft 47 core) is wired into `RunCommand`; the trace body preview bound is 2048 bytes.
- **When** the run completes successfully and `--trace trace.json` writes the artifact.
- **Then** a `trace.v1` artifact is written to `trace.json`; the artifact validates against the published `trace.v1` JSON Schema; `schemaVersion` is present; `run.workflowId` matches the executed workflow; `run.steps[]` contains two entries in execution order; each step records `attempt`, `startedAt`, `durationMs`, `status`, resolved request (method, URL, headers, body preview), and response (status, headers, bounded body preview).

**Scenario 2: Trace for a failed run identifies the failing step and criterion**
- **Given** an Arazzo document containing workflow index 0 where step index 1 (`stepId: "validate-payment"`) fails with `successCriteria` evaluating to `false` (criterion: `$statusCode == 200`, actual status 402); `arazzo run doc.arazzo --trace trace.json` is invoked.
- **When** the run fails and `--trace trace.json` writes the artifact.
- **Then** the artifact identifies the failing step (`stepId: "validate-payment"`); `run.steps[1].status` is `"failed"`; `run.steps[1].failureCategory` is set; the criterion outcome is `"not-met"` with reason `"Expected statusCode 200, got 402"`; `TraceSerializerTest` can consume this trace and reproduce the same failure.

**Scenario 3: Steps appear in execution order with timing and attempt data**
- **Given** a workflow with three steps where step 2 retries twice (attempts 1, 2, 3) before succeeding; step 3 depends on step 2; `--trace trace.json` is invoked.
- **When** the trace is generated after execution.
- **Then** `run.steps[]` contains entries in execution order (step-1, step-2 attempt 1, step-2 attempt 2, step-2 attempt 3, step-3); each step records `attempt` number, `startedAt` timestamp, `durationMs` elapsed, and `status`; step-2 has `attempts: 3`.

**Scenario 4: Secret headers are redacted**
- **Given** a request with header `Authorization: Bearer eyJhbGciOiJIUzI1NiJ9...secret-token`; `arazzo run doc.arazzo --trace trace.json` is invoked; secret redaction uses the fixed list (`Authorization`, `Cookie`, `Set-Cookie`, `Proxy-Authorization`) before draft 22 lands.
- **When** the trace is serialized by `TraceSerializer`.
- **Then** the `Authorization` header value is redacted to `<redacted>` in the trace artifact; the header key is preserved; the raw bearer token does not appear in the trace file; `TraceSerializerTest` verifies no secret strings appear in persisted artifacts.

**Scenario 5: Body previews respect the byte bound**
- **Given** an HTTP response with a 10 KiB body; `--trace-max-body-bytes` is set to 2048 (default); declared workflow outputs are 500 bytes.
- **When** the trace is generated.
- **Then** the response body preview is truncated at 2048 bytes; `run.steps[].response.bodyPreview` does not exceed 2048 bytes; declared workflow outputs are never truncated and appear in full in `run.outputs`.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `arazzo run --trace trace.json` writes a `trace.v1` artifact for both successful and failed runs
- [ ] The artifact validates against a published JSON Schema and includes `schemaVersion`
- [ ] Steps appear in execution order with `attempt`, `startedAt`, `durationMs` and `status`
- [ ] Each step records the resolved request method/url/headers/body preview
- [ ] Each step records the response status, headers and bounded body preview
- [ ] Each criterion records its outcome and reason
- [ ] Body previews respect `--trace-max-body-bytes`; declared outputs are never truncated
- [ ] Secret headers are redacted even before draft 22 lands
- [ ] A trace for a failed run is written and identifies the failing step and criterion
- [ ] The artifact is stable enough for replay: a replay test consumes it and reproduces the same outputs
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Error logging/audit trails instrumented via existing event system
- [ ] Core serializer (`trace.v1`) is draft 47; this draft is the CLI port only

### 🧬 10. Source Grounding & Code Location

- `packages/events/src/Listener/LedgerEventListener.php:42-77` — current payload scope.
- `packages/runtime/src/State/Data/StepResult.php:21-34` — request/response fields available but unexported.
- `packages/contracts/src/State/WorkflowContext.php:255-281` — request/response are attached to the step record.
- `packages/runtime/src/Telemetry/OtelSetup.php` — OTel is not a durable trace artifact.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/events/src/Listener/LedgerEventListener.php#L41-L79"><code>LedgerEventListener.php:41-79</code></a> @ <code>ea8c55a</code></summary>

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

- **Public API impact:** new optional CLI flag and a versioned artifact; event classes may gain fields additively.
- **Blast radius:** `packages/events`, `packages/runner`, `packages/runtime`, `packages/cli`.
- **Severity:** medium — diagnosability and replay depend on it.
- **Rollback:** drop the flag and artifact; no state migration.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Ledger payloads omit request/response/timing | T1 | `LedgerEventListener.php:42-77` | direct read |
| `StepResult` carries request/response/raw body/attempts | T1 | `StepResult.php:21-34` | direct read |
| Request/response are attached to the step record | T1 | `WorkflowContext.php:258-281` | direct read |
| No trace export option exists | T1 | `grep -rIn "trace" packages/cli/src/Console` → only a generator variable | executed |
| OTel spans are not persisted as an artifact | T1 | `OtelSetup.php` exports spans only | direct read |
