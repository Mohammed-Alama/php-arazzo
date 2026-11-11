# Draft issue 45 — Run report contract (`run-report.v1`)

- **Suggested title:** `[Feature] Define a versioned `run-report.v1` JSON contract for structured run results`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `runner`, `runtime`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/runner` `RunnerFacade` result shape; `packages/runtime` versioned artifact schemas.
- **Arazzo spec version:** independent of spec version; affects every `arazzo run` invocation regardless of entry point (CLI, MCP, UI).
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core; port-agnostic (usable from CLI, MCP, or UI).
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P1 — required for CI use and for agent/tool consumption.

### 📝 2. Problem Statement & Intent

`RunnerFacade::execute()` already returns a structured array with `workflowId`, `status`, `outputs`, `stepsSpent`, `workflowCallStack` and a `steps` map (`RunnerFacade.php:44-70`), but there is no versioned, stable contract for that shape. Without a defined contract:

- Every consumer must scrape human-oriented CLI text.
- MCP and UI entry points cannot rely on a stable result shape.
- The shape can drift silently between versions.

Expected: a published `run-report.v1` JSON Schema and a core serializer that turns a `RunnerFacade` result into that shape, so any entry point (CLI, MCP, UI) can emit structured run reports.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Define `run-report.v1` JSON Schema:
  - `schemaVersion`
  - `run.workflowId`, `run.status`, `run.startedAt`, `run.finishedAt`, `run.durationMs`
  - `run.inputs`, `run.outputs`
  - `run.steps[]`: `stepId`, `status`, `attempts`, `statusCode`, `outputs`, `failureCategory`, `error`, `durationMs`
  - `run.warnings[]`: stable code, message, step id
- A core `RunReportSerializer` (or equivalent) that turns a `RunnerFacade` result into `run-report.v1` shape.
- The serializer is dependency-free: no parsing, no execution, no transport.
- Redaction of secret-bearing values is deferred to draft 22; until then, `inputs`/`outputs` are emitted verbatim with a documented warning.
- `schemaVersion` is pinned so consumers can detect drift; breaking changes ship as `run-report.v2`.

**Non-goals**

- CLI flags (`--json`, `--export-run`, `schema run`) — tracked separately in draft 54.
- Replacing human-oriented CLI text output.
- JSON output for every other command (`validate` is #85).
- The full trace artifact with request/response bodies (draft 47 core).

**Prior art in this repository**

- `RunnerFacade::execute()` already returns a structured result (`RunnerFacade.php:44-70`).
- `CliRunResult::fromStatus()` exists for the async CLI worker (`packages/cli/src/Console/Cli/CliRunResult.php`).
- `#85` establishes the precedent for a JSON formatter on a command.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] A `run-report.v1` JSON Schema is published and validated against.
- [ ] `RunReportSerializer` turns a `RunnerFacade` result into `run-report.v1` shape.
- [ ] `run.status` is `succeeded` or `failed` and matches the process exit code.
- [ ] Every executed step appears in `run.steps` with `stepId`, `status`, `attempts`, `statusCode`, `outputs`, `durationMs` and `failureCategory` when failed.
- [ ] `run.outputs` contains the evaluated workflow outputs.
- [ ] `run.warnings[]` carries stable codes, messages and step ids.
- [ ] The serializer never sends network requests and never constructs a transport.
- [ ] `RunReporterTest` (or equivalent) fixture-tests a known document with deterministic output.
- [ ] Documentation includes the schema version and the stability promise.
- [ ] PHPStan max and Pint pass.
- [ ] **CLI port** (`--json`, `--export-run <path>`, `schema run` on `RunCommand`) is tracked separately in draft 54.

### 🧬 7. Source Grounding & Code Location

- `packages/runner/src/RunnerFacade.php:44-70` — structured result already available.
- `packages/cli/src/Console/Cli/CliRunResult.php` — existing result value object (for reference only).
- `packages/cli/src/Console/Command/RunCommand.php:173-189` — text-only rendering that the CLI port will augment.

<details><summary><code>RunnerFacade.php:44-70</code></summary>

```php
    private function baseResult(ExecutionResult $result): array
    {
        return [
            'workflowId' => $result->workflowId,
            'status' => $result->status,
            'outputs' => $result->outputs,
            'stepsSpent' => $result->stepsSpent,
            'workflowCallStack' => $result->workflowCallStack,
            'steps' => $result->steps,
        ];
    }
```

</details>

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive serializer and schema; no public contract break.
- **Blast radius:** `packages/runner`, `packages/runtime`; any entry point consuming run results.
- **Severity:** medium — without it, CI and agents cannot consume run results reliably.
- **Rollback:** remove the serializer; versioned schema means future changes ship as `v2`.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `RunnerFacade::execute()` returns structured result | T1 | `RunnerFacade.php:44-70` | direct read |
| No versioned run-report contract exists | T1 | grep for `run-report`/`RunReport` → no matches | executed grep |
| CLI writes text only for `run` | T1 | `RunCommand.php:173-189` | direct read |
| `#85` covers only `validate` formatters | T1 | issue #85 title and body | read |
| `RunReportSerializer` does not exist today | T1 | grep for `RunReportSerializer` → no matches | executed grep |