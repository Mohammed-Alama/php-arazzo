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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `Authorization`, `Proxy-Authorization`, `Cookie`, `Set-Cookie`, `X-Api-Key` and `X-Auth-Token` headers are redacted in state, ledger, trace and CLI output.
- [ ] Configured JSON Pointer paths are redacted in request and response bodies.
- [ ] Configured query parameters are redacted in recorded URLs and request records.
- [ ] The structure of the redacted record is preserved; only values change.
- [ ] `marker`, `hash` and `drop` modes produce deterministic output.
- [ ] Execution still uses the original, unredacted values.
- [ ] A redaction test fails if a known secret string appears in any persisted artifact or stdout.
- [ ] Redaction rules are configurable per host without code changes.
- [ ] Documentation states the limits of best-effort redaction.
- [ ] PHPStan max and Pint pass.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** new optional `Redactor` and configuration; persisted artifact content changes.
- **Blast radius:** every state save, ledger append, trace write and CLI output path.
- **Severity:** high — credential disclosure to disk, logs and support bundles.
- **Rollback:** disable additional rules; marker mode preserves shape, so downstream readers keep working.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| State is written unredacted | T1 | `FileStateStore.php:38-56` | direct read |
| Ledger inputs/outputs are verbatim | T1 | `LedgerEventListener.php:41-61` | direct read |
| Requests carry full headers into the step record | T1 | `WorkflowContext.php:258-267` | direct read |
| CLI prints outputs verbatim | T1 | `RunCommand.php:183-188` | direct read |
| No redaction code exists | T1 | `grep -rIn -i 'redact' packages/events/src packages/runtime/src packages/protocol-http/src packages/cli/src` → no matches | executed |
