# Draft issue 48 — Deterministic replay engine

- **Suggested title:** `[Feature] Core replay engine that re-executes a workflow from a recorded trace`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `runner`, `protocol-http`, `runtime`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/runner` execution; `packages/protocol-http` transport seam; `packages/runtime` state.
- **Arazzo spec version:** independent of spec version.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core; port-agnostic (usable from CLI, MCP, or UI).
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P2 — depends on traces (draft 47 core), but unlocks deterministic debugging and regression tests.

### 📝 2. Problem Statement & Intent

`CliRunner` has a `resume()` path that reloads persisted state and continues from the first non-succeeded step (`packages/cli/src/Console/Cli/CliRunner.php:138-170`). That is **resumption of a live run**, not replay. It still contacts the real transport for any step it re-executes, and it has no way to substitute recorded responses.

A recorded trace (draft 47 core) contains the request and response for every step. Nothing consumes it. As a result:

- a failure that depends on a specific server response cannot be reproduced offline;
- a flaky endpoint makes regression tests nondeterministic;
- a production incident cannot be re-run against the exact bytes that caused it without a live, possibly mutated, environment.

Expected: a core `ReplayTransport` (PSR-18 `ClientInterface` implementation) that serves recorded responses keyed by `(stepId, attempt)`, plus a replay engine that re-executes the recorded workflow without sending network requests.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `ReplayTransport` implements `ClientInterface` and returns recorded responses keyed by `(stepId, attempt)`.
- An unexpected request fails loudly with a stable error instead of falling through to the network.
- `--spec <path>` overrides `run.documentPath`; `--workflow-id <id>` overrides the recorded workflow (CLI port supplies flags).
- Every replayed step must produce the same status, outputs and criteria result as the trace. A mismatch is a replay failure with a diff.
- Replay accepts only records that contain a response; a trace captured with `--dry-run` is rejected as non-replayable.
- Replay never sends network traffic, even if a step is not present in the trace.
- Output matches the trace exactly; the command exits non-zero on any mismatch.

**Non-goals**

- CLI command (`arazzo replay`) — tracked separately in draft 57.
- Time-travel debugging or breakpoints (a DAP debugger is out of scope).
- Replaying non-HTTP protocols in the first iteration; HTTP/OpenAPI only.
- Editing recorded responses.

**Prior art in this repository**

- `CliRunner::resume()` proves state-driven continuation exists (`CliRunner.php:138-170`).
- `StepResult` carries the response that replay needs.
- `FakePsr18Client` and the conformance `FakerOpenApiExecutor` prove a deterministic transport seam is already a supported pattern; neither is a production replay transport.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `ReplayTransport` implements PSR-18 `ClientInterface` and returns recorded responses keyed by `(stepId, attempt)`.
- [ ] A step missing from the trace produces a stable `replay.missing_response` error, not a network call.
- [ ] A trace from a failed run replays the failure at the same step with the same criterion outcome.
- [ ] A trace captured under `--dry-run` is rejected with `replay.not_replayable`.
- [ ] Outputs match the trace exactly; replay exits non-zero on any mismatch.
- [ ] Replay never sends network traffic.
- [ ] `ReplayTransportTest` fixture-tests a recorded trace replayed end to end.
- [ ] PHPStan max and Pint pass.
- [ ] **CLI port** (`arazzo replay <trace.json>` command) is tracked separately in draft 57.

### 🧬 7. Source Grounding & Code Location

- `packages/cli/src/Console/Cli/CliRunner.php:138-170` — resume is not replay.
- `packages/protocol-http/src/Execution/Interfaces/OpenApiExecutorInterface.php` — the seam a replay transport must satisfy.
- `packages/runner/src/Execution/Data/StepResult.php:21-34` — response data recorded per step.

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

- **Public API impact:** new `ReplayTransport` class and replay engine; no public contract break.
- **Blast radius:** `packages/runner`, `packages/protocol-http` transport seams; no change to live execution when replay is not used.
- **Severity:** medium — debugging and deterministic regression testing.
- **Rollback:** remove the replay engine; traces remain useful on their own.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `resume()` continues a live run, not a replay | T1 | `CliRunner.php:138-170` | direct read |
| No replay transport exists | T1 | grep for `ReplayTransport` → no matches | executed grep |
| Step results carry the response a replay needs | T1 | `StepResult.php:21-34` | direct read |
| A deterministic fake transport is already a supported pattern | T1 | `FakePsr18Client`, `FakerOpenApiExecutor` | direct read |