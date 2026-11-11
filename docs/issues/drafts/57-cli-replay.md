# Draft issue 57 — CLI: `arazzo replay` deterministic replay from a trace

- **Suggested title:** `[Feature] `arazzo replay` — re-execute a workflow from a recorded trace`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `cli`, `runner`, `protocol-http`, `runtime`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` new command; `packages/runner` execution; `packages/protocol-http` transport seam; `packages/runtime` state.
- **Arazzo spec version:** independent of spec version.
- **PHP target:** 8.4.
- **Ecosystem:** CLI entry point only; the core replay engine lives in draft 48.
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P2 — depends on traces (draft 56 core), but unlocks deterministic debugging and regression tests.

### 📝 2. Problem Statement & Intent

Draft 48 core adds a `ReplayTransport` that serves recorded responses keyed by `(stepId, attempt)`. This draft exposes it via the CLI:

- `CliRunner` has a `resume()` path that reloads persisted state and continues from the first non-succeeded step (`packages/cli/src/Console/Cli/CliRunner.php:138-170`). That is **resumption of a live run**, not replay. It still contacts the real transport for any step it re-executes, and it has no way to substitute recorded responses.
- A recorded trace (draft 47 core) contains the request and response for every step. Nothing consumes it from the CLI.

Expected: `arazzo replay <trace.json>` re-executes the workflow with a transport that serves the recorded responses in order, without sending network requests.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `arazzo replay <trace.json>` reads a `trace.v1` artifact and executes the recorded workflow.
- A `ReplayTransport` (draft 48 core) implements the HTTP seam and returns recorded responses keyed by `(stepId, attempt)`; an unexpected request fails loudly with a stable error instead of falling through to the network.
- `--spec <path>` overrides `run.documentPath`; `--workflow-id <id>` overrides the recorded workflow.
- Every replayed step must produce the same status, outputs and criteria result as the trace. A mismatch is a replay failure with a diff.
- Replay accepts only records that contain a response; a trace captured with `--dry-run` is rejected as non-replayable.
- Replay never sends network traffic, even if a step is not present in the trace.
- Support `--json` output using the `run-report.v1` shape (draft 54).

**Non-goals**

- Time-travel debugging or breakpoints (a DAP debugger is out of scope).
- Replaying non-HTTP protocols in the first iteration; HTTP/OpenAPI only.
- Editing recorded responses.
- Core replay engine — that is draft 48.

**Prior art in this repository**

- `CliRunner::resume()` proves state-driven continuation exists (`CliRunner.php:138-170`).
- `StepResult` carries the response that replay needs.
- `FakePsr18Client` and the conformance `FakerOpenApiExecutor` prove a deterministic transport seam is already a supported pattern; neither is a production replay transport.
- Draft 48 core: `ReplayTransport` and replay engine.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `arazzo replay <trace.json>` runs the recorded workflow without any outbound request.
- [ ] The replay transport matches responses by step id and attempt.
- [ ] A step missing from the trace produces a stable `replay.missing_response` error, not a network call.
- [ ] A trace from a failed run replays the failure at the same step with the same criterion outcome.
- [ ] A trace captured under `--dry-run` is rejected with `replay.not_replayable`.
- [ ] Outputs match the trace exactly; the command exits non-zero on any mismatch.
- [ ] `--spec` and `--workflow-id` override the recorded values and are reflected in the report.
- [ ] Replay is covered by a fixture that runs once live (fake transport), records a trace, then replays it.
- [ ] PHPStan max and Pint pass.
- [ ] **Core engine** (`ReplayTransport`) is draft 48; this draft is the CLI port only.

### 🧬 7. Source Grounding & Code Location

- `packages/cli/src/Console/Cli/CliRunner.php:138-170` — resume is not replay.
- `packages/cli/src/Console/Command/RunCommand.php` — no `--trace`/replay equivalent.
- `packages/protocol-http/src/Execution/Interfaces/OpenApiExecutorInterface.php` — the seam a replay transport must satisfy.
- `packages/runner/src/Execution/Data/StepResult.php:21-34` — response data recorded per step.
- Draft 48 core: `ReplayTransport` and replay engine.

<details><summary><code>CliRunner.php:138-170</code></summary>

```php
public function resume(ArazzoDocument $document, string $executionId): CliRunResult
{
    $persisted = $this->stateStore->load($executionId);

    // ...
    foreach ($workflow->steps as $step) {
        if ($context->getStepStatus($step->stepId) !== StepStatus::Succeeded) {
            $this->queue->dispatch(new ExecuteStepJob($step, $context));

            break;
        }
    }

    return $this->drain($executionId);
}
```

</details>

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** new command; a new internal replay transport (draft 48 core).
- **Blast radius:** `packages/cli`, `packages/runner` transport seams; no change to live execution when replay is not used.
- **Severity:** medium — debugging and deterministic regression testing.
- **Rollback:** remove the command; traces remain useful on their own.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `resume()` continues a live run, not a replay | T1 | `CliRunner.php:138-170` | direct read |
| No replay command exists | T1 | grep for `replay` in `packages/cli/src` → no matches | executed grep |
| Step results carry the response a replay needs | T1 | `StepResult.php:21-34` | direct read |
| Draft 48 is the core dependency | T2 | draft 48 (replay engine) | cross-ref |