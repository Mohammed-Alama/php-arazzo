# Draft issue 33 — HAR 1.2 export of executed requests/responses

- **Suggested title:** `[Feature] Export a HAR 1.2 archive of executed requests/responses`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #24 Transport Failure Handling)
- **Packages:** `runner`, `contracts`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/runner` step results; `packages/contracts` `WorkflowContext::withStepRequest`/`withStepResponse` capture; `packages/runtime` `FileStateStore` persistence model.
- **Arazzo spec version:** independent of spec version; HAR is an output format, not a document construct.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core; port-agnostic (usable from CLI, MCP, or UI).
- **Kind / level:** `kind:feature`, `level:story`, under #24 (Transport Failure Handling).
- **Priority:** P2 — operators need a machine-readable archive of every request/response for debugging, support tickets, and replay; none exists.

### 📝 2. Problem Statement & Intent

php-arazzo captures request/response per step but never exports them as an archive:

- `WorkflowContext::withStepRequest()` / `withStepResponse()` store request/response records on each step (`WorkflowContext.php:258-281`).
- `StepExecutor` writes `withStepRequest` and `withStepResponse` per step (`StepExecutor.php:81-82`, `126-159`).
- `FileStateStore` persists the whole context as JSON (`FileStateStore.php:38-56`) but not in HAR format.
- No HAR serialization exists anywhere (`grep -rIn -i "har\b\|http archive" packages --exclude-dir=vendor` → no matches).

Expected: execution produces a HAR 1.2 archive containing one entry per executed step request, with request/response URLs, methods, headers, query strings, post data, response status, headers, body, and timings. Secret redaction (draft 22) applies to the archive at serialization time.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Execution produces a HAR 1.2 archive containing one entry per executed step request.
- Each entry: request (method, URL, httpVersion, cookies, headers, queryString, postData, headersSize, bodySize) and response (status, statusText, httpVersion, cookies, headers, body, redirectURL, headersSize, bodySize).
- Timings: `send`/`wait`/`receive` (best-effort; 0 if not measured).
- Bodies base64-encoded when binary per HAR spec; `content.text`/`content.encoding` set.
- Secret redaction (draft 22) applied to headers/bodies before serialization (do not redact in-memory execution values).

**Non-goals**

- HAR `cache` and detailed `timings` (server/connect) — best-effort only.
- `pageref` grouping — optional in HAR 1.2.
- Live HAR streaming — write-after-run only.
- CLI flag (`--har-output <path>` on `RunCommand`) — tracked separately in draft 43.

**Prior art in this repository**

- `packages/runner/src/Sync/StepExecutor.php:81-82` — `withStepRequest` recorded per step.
- `packages/runner/src/Sync/StepExecutor.php:126-159` — `withStepResponse` recorded per step.
- `packages/contracts/src/State/WorkflowContext.php:258-281` — `withStepRequest`/`withStepResponse` capture.
- `packages/runtime/src/State/FileStateStore.php:38-56` — existing JSON persistence pattern to model the file write after.

### 📄 4. Structural Artifacts & Raw Inputs

Expected HAR 1.2 (abbreviated):

```json
{
  "log": {
    "version": "1.2",
    "creator": { "name": "arazzo", "version": "1.0.0" },
    "entries": [
      {
        "startedDateTime": "2026-11-10T00:00:00Z",
        "time": 123,
        "request": {
          "method": "GET",
          "url": "https://api.example.com/orders/1",
          "httpVersion": "HTTP/1.1",
          "cookies": [],
          "headers": [],
          "queryString": [],
          "postData": null,
          "headersSize": -1,
          "bodySize": -1
        },
        "response": {
          "status": 200,
          "statusText": "OK",
          "httpVersion": "HTTP/1.1",
          "cookies": [],
          "headers": [],
          "body": { ... },
          "redirectURL": "",
          "headersSize": -1,
          "bodySize": -1
        },
        "cache": {},
        "timings": { "send": 0, "wait": 123, "receive": 0 }
      }
    ]
  }
}
```

### 🌀 5. Runtime Execution State Context

- Capture point: `StepExecutor` already records `withStepRequest`/`withStepResponse` per step; the exporter reads the final `WorkflowContext` (or `ExecutionResult` extended with request/response records).
- Serialization: a `HarSerializer` builds `log.entries[]` from step records; bodies base64-encoded when binary per HAR spec; redaction applied before serialization (draft 22).
- File write: the entry point (CLI, MCP, UI) decides where to write the archive.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] Execution exposes per-step request/response records suitable for HAR serialization.
- [ ] A HAR 1.2 serializer produces `log.version = "1.2"`, `creator`, and `entries[]`.
- [ ] One entry per executed step request.
- [ ] Request: method, URL, httpVersion, cookies, headers, queryString, postData, headersSize, bodySize.
- [ ] Response: status, statusText, httpVersion, cookies, headers, body, redirectURL, headersSize, bodySize.
- [ ] Timings: `send`/`wait`/`receive` (best-effort; 0 if not measured).
- [ ] Bodies base64-encoded when binary; `content.text`/`content.encoding` set per HAR.
- [ ] Secret redaction (draft 22) applied before serialization (do not redact in-memory execution values).
- [ ] `RunnerTest`/`HarSerializerTest` gains a case: HAR output valid 1.2 with one entry per step.
- [ ] HAR validates against minimal HAR 1.2 shape (`log.version`/`entries`).
- [ ] PHPStan max and Pint pass.
- [ ] **CLI port** (`--har-output <path>` flag on `RunCommand`) is tracked separately in draft 43.

### 🧬 7. Source Grounding & Code Location

- `packages/runner/src/Sync/StepExecutor.php:81-82` — `withStepRequest` recorded.
- `packages/runner/src/Sync/StepExecutor.php:126-159` — `withStepResponse` recorded with statusCode/headers/body.
- `packages/contracts/src/State/WorkflowContext.php:229` — `getSteps()` returns step records with `request`/`response`.
- `packages/contracts/src/State/WorkflowContext.php:258-281` — `withStepRequest`/`withStepResponse`.
- `packages/runtime/src/State/FileStateStore.php:38-56` — existing JSON persistence pattern to model the file write after.

<details><summary><code>StepExecutor.php:81-82</code></summary>

```php
        $context = $context->withStepRequest($step->stepId, $outcome->request ?? []);
        $context = $context->withStepResponse($step->stepId, [
```

</details>

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive serialization capability; new `HarSerializer` class (internal).
- **Blast radius:** `packages/runner` execution; persisted artifact content (HAR file). Secret redaction intersects with draft 22.
- **Severity:** medium — debuggability; no behaviour change by default.
- **Rollback:** remove serializer; no data migration.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No HAR code anywhere | T1 | `grep -rIn -i "har\b\|http archive" packages --exclude-dir=vendor` → no matches | executed grep |
| Request/response captured per step | T1 | `StepExecutor.php:81-82,126-159`; `WorkflowContext.php:258-281` | direct read |
| HAR 1.2 is an external transport-archive spec | T1 | HAR 1.2 specification | external spec |