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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `RunCommand` gains `--trace <path>` option.
- [ ] `TraceSerializer` (draft 47 core) produces `trace.v1` artifacts for both successful and failed runs.
- [ ] Steps appear in execution order with `attempt`, `startedAt`, `durationMs` and `status`.
- [ ] Each step records the resolved request method/url/headers/body preview.
- [ ] Each step records the response status, headers and bounded body preview.
- [ ] Each criterion records its outcome and reason.
- [ ] Body previews respect `--trace-max-body-bytes`; declared outputs are never truncated.
- [ ] Secret headers are redacted before serialization.
- [ ] A trace for a failed run is written and identifies the failing step and criterion.
- [ ] `RunCommandTest` fixture-tests trace output for a successful and failed run.
- [ ] PHPStan max and Pint pass.
- [ ] **Core serializer** (`trace.v1`) is draft 47; this draft is the CLI port only.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive CLI option; new trace artifact (draft 47 core).
- **Blast radius:** `packages/cli` `run`; persisted artifact content (trace file). Secret redaction intersects with draft 22.
- **Severity:** medium — diagnosability and replay depend on it.
- **Rollback:** remove option; no data migration.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No `--trace` option | T1 | `RunCommand.php:45-51` | direct read |
| Ledger payloads omit request/response/timing | T1 | `LedgerEventListener.php:42-77` | direct read |
| `StepResult` carries request/response/raw body/attempts | T1 | `StepResult.php:21-34` | direct read |
| Draft 47 is the core dependency | T2 | draft 47 (trace serializer) | cross-ref |