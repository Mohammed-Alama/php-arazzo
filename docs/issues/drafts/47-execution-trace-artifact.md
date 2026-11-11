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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] A `trace.v1` JSON Schema is published and validated against.
- [ ] `TraceSerializer` builds `trace.v1` artifacts from step records.
- [ ] Steps appear in execution order with `attempt`, `startedAt`, `durationMs` and `status`.
- [ ] Each step records the resolved request method/url/headers/body preview.
- [ ] Each step records the response status, headers and bounded body preview.
- [ ] Each criterion records its outcome and reason.
- [ ] Body previews respect the byte bound; declared outputs are never truncated.
- [ ] Secret headers are redacted even before draft 22 lands.
- [ ] A trace for a failed run is written and identifies the failing step and criterion.
- [ ] The artifact is stable enough for replay: a replay test (draft 48) consumes it and reproduces the same outputs.
- [ ] `TraceSerializerTest` fixture-tests trace shape for successful and failed runs.
- [ ] PHPStan max and Pint pass.
- [ ] **CLI port** (`--trace <path>` on `RunCommand`) is tracked separately in draft 56.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** new serializer and schema; event classes may gain fields additively.
- **Blast radius:** `packages/events`, `packages/runner`, `packages/runtime`; any entry point consuming traces.
- **Severity:** medium — diagnosability and replay depend on it.
- **Rollback:** drop the serializer; no state migration.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Ledger payloads omit request/response/timing | T1 | `LedgerEventListener.php:42-77` | direct read |
| `StepResult` carries request/response/raw body/attempts | T1 | `StepResult.php:21-34` | direct read |
| Request/response are attached to the step record | T1 | `WorkflowContext.php:258-281` | direct read |
| No trace serializer exists today | T1 | grep for `TraceSerializer` → no matches | executed grep |
| OTel spans are not persisted as an artifact | T1 | `OtelSetup.php` exports spans only | direct read |