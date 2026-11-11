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

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Define a versioned `run-report.v1` JSON contract and a core serializer that turns a `RunnerFacade` result into that shape, so any entry point (CLI, MCP, UI) can emit structured run reports.

**Architectural Boundary:** Bounded context = run result serialization. Packages: `packages/runner` (`RunnerFacade` result shape), `packages/runtime` (versioned artifact schemas). Port-agnostic; CLI port tracked in draft 54.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Serialization is O(result size); dependency-free serializer (no parsing, execution, or transport).
- [ ] **Flexible:** Versioned schema (`run-report.v1`) allows future breaking changes as `v2`; additive serializer and schema.
- [ ] **Maintainable:** `RunReportSerializer` single-responsibility; stable error codes; deterministic output.
- [ ] **Operable:** `schemaVersion` pinned for drift detection; rollback by removing serializer (versioned schema means future changes as `v2`).
- [ ] **Reliable:** `run.status` matches process exit code; deterministic; idempotent across runs.
- [ ] **Safe:** Read-only serialization; no network requests; no transport construction.
- [ ] **Secure:** Redaction deferred to draft 22; until then `inputs`/`outputs` emitted verbatim with documented warning.
- [ ] **Suitable:** Required for CI use and agent/tool consumption; provides workflow id, status, per-step status, outputs, failure category, attempts, warnings.
- [ ] **Usable:** `run-report.v1` JSON Schema published; consumers can validate against it.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `run-report.v1` JSON Schema published**
- **Given** a `RunnerFacade` execution result containing `workflowId`, `status`, `outputs`, `steps[]`, `stepsSpent`, `workflowCallStack`; `RunReportSerializer` (draft 45 core) converts the result to `run-report.v1` shape; `run-report.v1` JSON Schema is published.
- **When** `RunReportSerializer::serialize($result)` is called.
- **Then** output validates against the published `run-report.v1` JSON Schema; the serialized output contains `schemaVersion`, `run.workflowId`, `run.status`, `run.startedAt`, `run.finishedAt`, `run.durationMs`, `run.inputs`, `run.outputs`, `run.steps[]`, `run.warnings[]`.

**Scenario 2: `run.status` matches exit code**
- **Given** a failed workflow (step failure or unmet `successCriteria`); `RunnerFacade::execute()` returns `status: "failed"`; `RunReportSerializer` serializes the result.
- **When** the report is serialized.
- **Then** `run.status` is `"failed"`; the process exit code is non-zero; `run.status` matches the process exit code; `ValidationResult::isValid()` reflects the failed status.

**Scenario 3: Every step appears in `run.steps`**
- **Given** a workflow with multiple steps (`step-1`, `step-2`, `step-3`); each step has `stepId`, `status`, `attempts`, `statusCode`, `outputs`, `durationMs`; failed steps have `failureCategory`.
- **When** `RunReportSerializer` serializes the result.
- **Then** `run.steps[]` contains one entry per step; each entry has `stepId`, `status`, `attempts`, `statusCode`, `outputs`, `durationMs`; `failureCategory` is present when a step failed; steps appear in execution order.

**Scenario 4: `run.outputs` contains evaluated outputs**
- **Given** a workflow with evaluated outputs (`{"orderId": "ORD-123", "total": 99.99}`); `RunnerFacade::execute()` returns the outputs.
- **When** `RunReportSerializer` serializes the result.
- **Then** `run.outputs` contains the evaluated workflow outputs; `run.outputs.orderId` is `"ORD-123"`; `run.outputs.total` is `99.99`.

**Scenario 5: `run.warnings[]` carries stable codes**
- **Given** a run with warnings (e.g., transport redirect warning, deprecation warning); each warning has a stable code, message, and optional step id.
- **When** `RunReportSerializer` serializes the result.
- **Then** `run.warnings[]` contains entries with stable codes (e.g., `transport.redirect_limit`), messages, and step ids; each warning entry has `code`, `message`, and optional `stepId`.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `run-report.v1` JSON Schema published and validated against
- [ ] `RunReportSerializer` turns `RunnerFacade` result into `run-report.v1` shape
- [ ] `run.status` is `succeeded` or `failed` matching process exit code
- [ ] Every executed step in `run.steps` with required fields
- [ ] `run.outputs` contains evaluated workflow outputs
- [ ] `run.warnings[]` carries stable codes, messages, step ids
- [ ] Serializer never sends network requests or constructs transport
- [ ] `RunReporterTest` fixture-tests known document with deterministic output
- [ ] Documentation includes schema version and stability promise
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] CLI port (`--json`, `--export-run`, `schema run`) tracked separately in draft 54

### 🧬 10. Source Grounding & Code Location

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

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive serializer and schema; no public contract break.
- **Blast radius:** `packages/runner`, `packages/runtime`; any entry point consuming run results.
- **Severity:** medium — without it, CI and agents cannot consume run results reliably.
- **Rollback:** remove the serializer; versioned schema means future changes ship as `v2`.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `RunnerFacade::execute()` returns structured result | T1 | `RunnerFacade.php:44-70` | direct read |
| No versioned run-report contract exists | T1 | grep for `run-report`/`RunReport` → no matches | executed grep |
| CLI writes text only for `run` | T1 | `RunCommand.php:173-189` | direct read |
| `#85` covers only `validate` formatters | T1 | issue #85 title and body | read |
| `RunReportSerializer` does not exist today | T1 | grep for `RunReportSerializer` → no matches | executed grep |