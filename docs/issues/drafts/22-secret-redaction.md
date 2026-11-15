# Draft issue 22 — Secret redaction for events, state, traces and CLI output

- **Suggested title:** `[Feature] Redact secrets from persisted state, events, traces and CLI output`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `events`, `runtime`, `runner`, `cli`, `contracts`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/events` ledger listener; `packages/runtime` file/state stores; `packages/runner` step records; `packages/cli` output; `packages/contracts` redaction vocabulary.
- **Arazzo spec version:** independent of spec version.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core plus CLI.
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P2 — security and compliance; becomes urgent as soon as traces/state are shared.

### 📝 2. Problem Statement & Intent

Secrets flow through several persistence and output paths with no redaction:

- `WorkflowContext::withStepRequest()` stores the full request record on the step
  (`packages/contracts/src/State/WorkflowContext.php:255-267`).
- `StepResult` stores request/response bodies and headers
  (`packages/runtime/src/State/Data/StepResult.php:21-34`).
- `FileStateStore::save()` writes the whole context as pretty JSON
  (`packages/runtime/src/State/FileStateStore.php:38-56`).
- `LedgerEventListener` writes `run.started` inputs and `run.completed` outputs
  verbatim (`packages/events/src/Listener/LedgerEventListener.php:41-61`).
- `RunCommand` prints workflow outputs verbatim (`RunCommand.php:183-188`).

A workflow that sends `Authorization: Bearer …`, an API key query parameter or a
password body therefore writes that value to disk, to the event ledger and to
stdout. Traces (draft 14) and replay (draft 15) would multiply the exposure.

Expected: a single redaction layer applied at every persistence and export
boundary, with an explicit, documented vocabulary and deterministic markers.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `Redactor` service with precedence:
  1. exact header names (case-insensitive): `Authorization`, `Proxy-Authorization`, `Cookie`, `Set-Cookie`, `X-Api-Key`, `X-Auth-Token`
  2. JSON Pointer paths configured per workflow/step (e.g. `/password`, `/token`)
  3. query parameter names (`token`, `key`, `api_key`, `password`, `secret`, `access_token`)
  4. optional regex rules configured by the host
- Marker: `<redacted>` by default; `--redaction-mode <marker|hash|drop>` allows
  a stable hash or removal.
- Apply before: state save/load export, event ledger append, trace write,
  replay failure diff, CLI JSON and human output.
- Do not redact in-memory values used for execution; redaction is a boundary
  concern only.
- Preserve structure: redact the value, keep the key, so replay and schema
  validation can still see the shape.
- `--no-redact` is **not** offered; hosts may configure additional rules.
- Document that redaction is best-effort and cannot detect arbitrary secrets
  embedded in free-form bodies without rules.

**Non-goals**

- DLP classification or entropy-based secret detection.
- Encrypting stored state.
- Redacting telemetry exported to an external OTel collector (track separately
  if needed).
- Changing the Arazzo document model.

**Prior art in this repository**

- `FileStateStore` is the only durable state writer.
- `LedgerEventListener` is the only ledger writer.
- `StepResult` is the shape both consume.
- The Rust `redaction-vocabulary-contract` assessment confirms the categories
  and the marker/hash/drop choice are the right axes.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Apply a single redaction layer at every persistence and export boundary (state, events, traces, CLI output) to prevent secrets from leaking into persisted artifacts, event ledgers, traces, and stdout.

**Architectural Boundary:** Bounded context = secret redaction + persistence/output boundaries. Packages: `packages/events` (ledger listener), `packages/runtime` (file/state stores), `packages/runner` (step records), `packages/cli` (output), `packages/contracts` (redaction vocabulary). Redaction is a boundary concern only — in-memory execution values are unredacted.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Redaction applied once at serialization boundaries; O(size of artifact); no impact on in-memory execution performance.
- [ ] **Flexible:** `Redactor` service with configurable precedence (exact headers, JSON Pointer paths, query parameters, optional regex); marker/hash/drop modes; per-host configuration without code changes.
- [ ] **Maintainable:** Single-responsibility `Redactor` service; deterministic output; marker preserves structure so downstream readers keep working.
- [ ] **Operable:** `--redaction-mode <marker|hash|drop>`; no `--no-redact` offered; best-effort redaction documented as a limitation.
- [ ] **Reliable:** Deterministic redaction output; idempotent across runs; structure preserved; only values change.
- [ ] **Safe:** Execution uses original unredacted values; redaction only at persistence/export boundaries; no state corruption.
- [ ] **Secure:** Redacts `Authorization`, `Proxy-Authorization`, `Cookie`, `Set-Cookie`, `X-Api-Key`, `X-Auth-Token` headers; configured JSON Pointer paths; configured query parameters; best-effort (cannot detect arbitrary secrets in free-form bodies).
- [ ] **Suitable:** Closes the gap where secrets flow through state stores, event ledgers, traces, and CLI output verbatim.
- [ ] **Usable:** `<redacted>` marker by default; `--redaction-mode` for hash or drop; rules configurable per host; documented limits.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: Secret headers redacted in state, ledger, trace and CLI output**
- **Given** an HTTP request with header `Authorization: Bearer eyJhbGciOiJIUzI1NiJ9...secret-token`; `Redactor` service is configured with exact header names (case-insensitive): `Authorization`, `Proxy-Authorization`, `Cookie`, `Set-Cookie`, `X-Api-Key`, `X-Auth-Token`; marker mode is `<redacted>`; `FileStateStore`, `LedgerEventListener`, `TraceSerializer`, and `RunCommand` output paths are active.
- **When** the artifact (state file, event ledger, trace artifact, or CLI stdout) is serialized by `Redactor::redact()`.
- **Then** the `Authorization` header value is redacted to `<redacted>`; the header key is preserved; the redacted value appears in `FileStateStore::save()` output, `LedgerEventListener` payload, trace artifact, and CLI stdout; `ErrorCollector` is not involved (redaction is at serialization boundary).

**Scenario 2: Configured JSON Pointer paths redacted in request/response bodies**
- **Given** a redaction rule configured for JSON Pointer path `/password`; a request body contains `{"username": "alice", "password": "s3cr3t", "token": "abc123"}`; redaction rules are configurable per host without code changes.
- **When** `Redactor::redact()` processes the request/response body before serialization.
- **Then** the body is serialized as `{"username": "alice", "password": "<redacted>", "token": "abc123"}`; only the value at `/password` is changed; the structure (keys) is preserved.

**Scenario 3: Configured query parameters redacted in recorded URLs**
- **Given** a redaction rule configured for query parameter `access_token`; a request URL is `https://api.example.com/callback?access_token=abc123&state=xyz`.
- **When** the URL is recorded in `StepResult` or trace artifact.
- **Then** the URL is recorded as `https://api.example.com/callback?access_token=<redacted>&state=xyz`; the `access_token` value is redacted; other query parameters are preserved.

**Scenario 4: Execution still uses original unredacted values**
- **Given** redaction rules configured for `Authorization` header and `/password` JSON Pointer; the workflow executes an HTTP step with real credentials.
- **When** the workflow executes the step (actual network request via `DefaultOpenApiExecutor`).
- **Then** the actual request sent over the network contains the real secret values (`Authorization: Bearer eyJ...`, `password: s3cr3t`); redaction is a boundary-only concern applied at persistence/export, not at execution; in-memory execution values are unredacted.

**Scenario 5: Marker, hash, and drop modes produce deterministic output**
- **Given** the same secret value `s3cr3t-p@ssw0rd` and each redaction mode configured: `marker`, `hash`, `drop`.
- **When** artifacts are serialized with each mode.
- **Then** `marker` mode produces `<redacted>`; `hash` mode produces a stable hash (e.g., `sha256(s3cr3t-p@ssw0rd) = a1b2c3...`) that is deterministic across runs; `drop` mode removes the value entirely (key preserved, value omitted); all modes produce deterministic output across repeated serializations.

**Scenario 6: Redaction test fails if secret appears in persisted artifact**
- **Given** a test that runs a workflow with a known secret `sk-test-1234567890abcdef` in an `Authorization` header; the workflow executes and produces state, ledger, trace, and CLI output artifacts.
- **When** the artifacts are persisted to disk or printed to stdout.
- **Then** a redaction test scans all persisted artifacts and stdout for the string `sk-test-1234567890abcdef`; if found, the test fails; no known secret string appears in any persisted artifact or stdout.

**Scenario 7: Redaction rules configurable per host without code changes**
- **Given** a host configuration file (e.g., `arazzo.redact.php`) that specifies additional redaction rules (e.g., JSON Pointer `/apiKey`, query parameter `access_token`); no code changes are required to add new rules.
- **When** `Redactor` is initialized with the host configuration.
- **Then** the additional rules are loaded and applied; `X-Custom-Token` header and `/apiKey` JSON Pointer are redacted; rules are configurable per host without modifying `Redactor` source code.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `Authorization`, `Proxy-Authorization`, `Cookie`, `Set-Cookie`, `X-Api-Key`, `X-Auth-Token` headers redacted in state, ledger, trace and CLI output
- [ ] Configured JSON Pointer paths redacted in request and response bodies
- [ ] Configured query parameters redacted in recorded URLs and request records
- [ ] Structure of redacted record preserved; only values change
- [ ] `marker`, `hash`, `drop` modes produce deterministic output
- [ ] Execution still uses the original, unredacted values
- [ ] Redaction test fails if a known secret string appears in any persisted artifact or stdout
- [ ] Redaction rules configurable per host without code changes
- [ ] Documentation states the limits of best-effort redaction
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] No telemetry/logging changes needed (redaction is at serialization boundaries)

### 🧬 10. Source Grounding & Code Location

- `packages/runtime/src/State/FileStateStore.php:38-56` — full context written to disk.
- `packages/events/src/Listener/LedgerEventListener.php:41-61` — inputs/outputs written verbatim.
- `packages/contracts/src/State/WorkflowContext.php:255-281` — request/response attached to step records.
- `packages/cli/src/Console/Command/RunCommand.php:183-188` — outputs printed verbatim.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/runtime/src/State/FileStateStore.php#L35-L47"><code>FileStateStore.php:35-47</code></a> @ <code>ea8c55a</code></summary>

```php
public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
{
    $json = json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    $bytesWritten = file_put_contents($this->path($executionId), $json);

    if ($bytesWritten === false) {
        throw new RuntimeException("Could not persist execution state: {$executionId}");
    }
}
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** new optional `Redactor` and configuration; persisted artifact content changes.
- **Blast radius:** every state save, ledger append, trace write and CLI output path.
- **Severity:** high — credential disclosure to disk, logs and support bundles.
- **Rollback:** disable additional rules; marker mode preserves shape, so downstream readers keep working.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| State is written unredacted | T1 | `FileStateStore.php:38-56` | direct read |
| Ledger inputs/outputs are verbatim | T1 | `LedgerEventListener.php:41-61` | direct read |
| Requests carry full headers into the step record | T1 | `WorkflowContext.php:258-267` | direct read |
| CLI prints outputs verbatim | T1 | `RunCommand.php:183-188` | direct read |
| No redaction code exists | T1 | `grep -rIn -i 'redact' packages/events/src packages/runtime/src packages/protocol-http/src packages/cli/src` → no matches | executed |
