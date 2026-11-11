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

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Export executed requests/responses as a HAR 1.2 archive so operators have a machine-readable archive for debugging, support tickets, and replay.

**Architectural Boundary:** Bounded context = execution output + archive serialization. Packages: `packages/runner` (step results), `packages/contracts` (`WorkflowContext::withStepRequest`/`withStepResponse`). Port-agnostic; CLI port tracked in draft 43. Secret redaction (draft 22) applies at serialization time.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** HAR written once after run; per-step entries; no streaming overhead; bodies base64-encoded when binary.
- [ ] **Flexible:** `HarSerializer` additive class; HAR 1.2 format; secret redaction delegated to draft 22; best-effort timings (0 if not measured).
- [ ] **Maintainable:** Single-responsibility serializer; models existing `FileStateStore` JSON persistence pattern; `RunnerTest`/`HarSerializerTest` gains cases.
- [ ] **Operable:** Write-after-run only; entry point (CLI/MCP/UI) decides file location; rollback by removing serializer (no data migration).
- [ ] **Reliable:** One entry per executed step request; deterministic HAR shape; validates against minimal HAR 1.2 shape.
- [ ] **Safe:** Read-only serialization; no state mutation; redaction does not affect in-memory execution values.
- [ ] **Secure:** Secret redaction (draft 22) applied before serialization; no credentials in plaintext in the archive.
- [ ] **Suitable:** Closes the gap where request/response data is captured per step but never exported as an archive.
- [ ] **Usable:** HAR 1.2 is an established format; tooling exists for HAR analysis; one entry per step.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: HAR 1.2 archive produced with one entry per step**
- **Given** an Arazzo document (`doc.arazzo`) containing workflow index 0 with three HTTP steps (step-1, step-2, step-3); `HarSerializer` (draft 33 core) is wired into `RunCommand`; `--har-output run.har` is passed; `StepResult` captures request/response per step via `WorkflowContext::withStepRequest()`/`withStepResponse()`.
- **When** the run completes and HAR export is requested via `arazzo run doc.arazzo --har-output run.har`.
- **Then** a HAR 1.2 archive is produced at `run.har`; `log.version` is `"1.2"`; `creator` is `{"name": "arazzo", "version": "1.0.0"}`; `log.entries[]` contains exactly one entry per executed step request (3 entries); each entry has `startedDateTime`, `time`, `request`, `response`, `cache`, `timings`.

**Scenario 2: Request fields populated per HAR spec**
- **Given** an executed step with an HTTP request (`GET https://api.example.com/items?filter=active`, headers `Accept: application/json`, no body).
- **When** `HarSerializer` serializes the HAR entry for this step.
- **Then** the request object contains: `method: "GET"`, `url: "https://api.example.com/items?filter=active"`, `httpVersion: "HTTP/1.1"`, `cookies: []`, `headers: [{name: "Accept", value: "application/json"}]`, `queryString: [{name: "filter", value: "active"}]`, `postData: null`, `headersSize: -1`, `bodySize: -1`.

**Scenario 3: Response fields populated per HAR spec**
- **Given** an executed step with an HTTP response (status 200, `Content-Type: application/json`, body `{"id": 1, "name": "item1"}`).
- **When** `HarSerializer` serializes the HAR entry for this step.
- **Then** the response object contains: `status: 200`, `statusText: "OK"`, `httpVersion: "HTTP/1.1"`, `cookies: []`, `headers: [{name: "Content-Type", value: "application/json"}]`, `body: {"id": 1, "name": "item1"}`, `redirectURL: ""`, `headersSize: -1`, `bodySize: <byte count>`.

**Scenario 4: Timings best-effort**
- **Given** a step without detailed timing measurements (only total duration is known); `HarSerializer` uses best-effort timing.
- **When** `HarSerializer` serializes the `timings` object for this step.
- **Then** `timings` is `{"send": 0, "wait": <measured_duration_ms>, "receive": 0}`; `send` and `receive` are 0 (not measured); `wait` contains the measured step duration; timings are best-effort.

**Scenario 5: Bodies base64-encoded when binary**
- **Given** an executed step with a binary response body (e.g., image/png); `HarSerializer` follows HAR 1.2 spec for binary content.
- **When** `HarSerializer` serializes the response body.
- **Then** the response body is base64-encoded; `content.text` contains the base64 string; `content.encoding` is `"base64"`; `content.size` and `content.compression` are set per HAR spec.

**Scenario 6: Secret redaction applied before serialization**
- **Given** an executed step with request header `Authorization: Bearer sk-test-1234567890abcdef`; `Redactor` (draft 22 core) is applied before serialization; `HarSerializer` integrates with `Redactor`.
- **When** `HarSerializer` serializes the HAR entry including request headers.
- **Then** the `Authorization` header value in the HAR archive is `<redacted>`; the token `sk-test-1234567890abcdef` does not appear in plaintext in the HAR file; the header key `Authorization` is preserved; secret redaction does not affect in-memory execution values.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] Execution exposes per-step request/response records suitable for HAR serialization
- [ ] HAR 1.2 serializer produces `log.version = "1.2"`, `creator`, `entries[]`
- [ ] One entry per executed step request
- [ ] Request: method, URL, httpVersion, cookies, headers, queryString, postData, headersSize, bodySize
- [ ] Response: status, statusText, httpVersion, cookies, headers, body, redirectURL, headersSize, bodySize
- [ ] Timings: `send`/`wait`/`receive` (best-effort; 0 if not measured)
- [ ] Bodies base64-encoded when binary; `content.text`/`content.encoding` set per HAR
- [ ] Secret redaction (draft 22) applied before serialization (do not redact in-memory execution values)
- [ ] `RunnerTest`/`HarSerializerTest` case: HAR output valid 1.2 with one entry per step
- [ ] HAR validates against minimal HAR 1.2 shape (`log.version`/`entries`)
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] CLI port (`--har-output <path>`) tracked separately in draft 43

### 🧬 10. Source Grounding & Code Location

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

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive serialization capability; new `HarSerializer` class (internal).
- **Blast radius:** `packages/runner` execution; persisted artifact content (HAR file). Secret redaction intersects with draft 22.
- **Severity:** medium — debuggability; no behaviour change by default.
- **Rollback:** remove serializer; no data migration.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No HAR code anywhere | T1 | `grep -rIn -i "har\b\|http archive" packages --exclude-dir=vendor` → no matches | executed grep |
| Request/response captured per step | T1 | `StepExecutor.php:81-82,126-159`; `WorkflowContext.php:258-281` | direct read |
| HAR 1.2 is an external transport-archive spec | T1 | HAR 1.2 specification | external spec |