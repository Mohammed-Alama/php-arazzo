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

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Provide a core `ReplayTransport` (PSR-18 `ClientInterface`) that serves recorded responses keyed by `(stepId, attempt)`, plus a replay engine that re-executes a workflow without sending network requests.

**Architectural Boundary:** Bounded context = replay engine + transport seam. Packages: `packages/runner` (execution), `packages/protocol-http` (transport seam), `packages/runtime` (state). Port-agnostic; CLI port tracked in draft 57. Depends on traces (draft 47 core).

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Recorded responses served from memory; O(1) lookup by `(stepId, attempt)`; no network I/O during replay.
- [ ] **Flexible:** `ReplayTransport` implements `ClientInterface` (swappable seam); `--spec`/`--workflow-id` overrides (CLI port supplies flags).
- [ ] **Maintainable:** Single-responsibility replay transport; stable errors (`replay.missing_response`, `replay.not_replayable`); typed failures.
- [ ] **Operable:** Replay never sends network traffic; rollback by removing replay engine (traces remain useful); traces from `--dry-run` rejected.
- [ ] **Reliable:** Every replayed step produces same status/outputs/criteria as trace; mismatch is replay failure with diff; exits non-zero on mismatch.
- [ ] **Safe:** No network traffic during replay; `--dry-run` traces rejected as non-replayable; no side effects beyond report generation.
- [ ] **Secure:** No new authorization surface; replay is read-only against recorded data.
- [ ] **Suitable:** Unlocks deterministic debugging and regression tests; depends on traces (draft 47 core).
- [ ] **Usable:** Replay transport matches responses by step id and attempt; fixture test replays recorded trace end to end.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `ReplayTransport` implements PSR-18 `ClientInterface`**
- **Given** a recorded `trace.v1` artifact with responses keyed by `(stepId, attempt)`; `ReplayTransport` (draft 48 core) implements PSR-18 `ClientInterface`; `arazzo replay trace.json` is invoked.
- **When** `ReplayTransport::sendRequest($request)` is called during replay execution.
- **Then** responses are returned keyed by `(stepId, attempt)` from the recorded trace; no network traffic is sent; each step receives the recorded response for its attempt number.

**Scenario 2: Missing step produces stable error**
- **Given** a step not present in the trace (e.g., step `validate-payment` is in the workflow but not in the recorded trace); `ReplayTransport` looks up `(stepId: "validate-payment", attempt: 1)`.
- **When** replay reaches the missing step.
- **Then** a stable `replay.missing_response` error is returned; no network call is made; `ErrorCollector` records the error under `errors`; `ValidationResult::isValid()` returns `false`.

**Scenario 3: Failed run replays same failure**
- **Given** trace from a failed run where step `validate-payment` failed with criterion `not-met` (condition: `$statusCode == 200`, actual status 402); `arazzo replay trace-failed.json` is invoked.
- **When** `ReplayTransport` replays the recorded workflow.
- **Then** the failure replays at the same step (`validate-payment`) with the same criterion outcome (`not-met`); the criterion reason ("Expected statusCode 200, got 402") matches the original; `ReplayTransportTest` verifies replayed outputs match the trace.

**Scenario 4: `--dry-run` trace rejected**
- **Given** a trace captured under `--dry-run` (no response frames recorded, only request plans); `ReplayTransport` validates that the trace contains response frames.
- **When** `arazzo replay trace-dryrun.json` is attempted.
- **Then** the trace is rejected with error `replay.not_replayable`; `ErrorCollector` records the error; `ValidationResult::isValid()` returns `false`; no execution is attempted.

**Scenario 5: Outputs match trace exactly**
- **Given** a recorded `trace.v1` artifact with workflow outputs (`{"orderId": "ORD-123", "total": 99.99}`); `arazzo replay trace.json` is invoked.
- **When** `ReplayTransport` replays the workflow and produces outputs.
- **Then** replayed outputs match the trace exactly (`orderId: "ORD-123"`, `total: 99.99`); any mismatch causes a non-zero exit code; `ReplayTransportTest` runs once live (fake transport), records a trace, then replays it and asserts output equality.

**Scenario 6: Replay never sends network traffic**
- **Given** any replay execution using `ReplayTransport`; the transport is configured to serve only recorded responses.
- **When** `arazzo replay trace.json` executes the full workflow.
- **Then** zero outbound network requests are sent; all responses come from the recorded trace; if a step is not in the trace, `replay.missing_response` error is returned instead of sending a network request.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `ReplayTransport` implements PSR-18 `ClientInterface` returning recorded responses keyed by `(stepId, attempt)`
- [ ] Step missing from trace produces stable `replay.missing_response` error, not network call
- [ ] Trace from failed run replays failure at same step with same criterion outcome
- [ ] Trace captured under `--dry-run` rejected with `replay.not_replayable`
- [ ] Outputs match trace exactly; replay exits non-zero on mismatch
- [ ] Replay never sends network traffic
- [ ] `ReplayTransportTest` fixture-tests recorded trace replayed end to end
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] CLI port (`arazzo replay <trace.json>` command) tracked separately in draft 57

### 🧬 10. Source Grounding & Code Location

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

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** new `ReplayTransport` class and replay engine; no public contract break.
- **Blast radius:** `packages/runner`, `packages/protocol-http` transport seams; no change to live execution when replay is not used.
- **Severity:** medium — debugging and deterministic regression testing.
- **Rollback:** remove the replay engine; traces remain useful on their own.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `resume()` continues a live run, not a replay | T1 | `CliRunner.php:138-170` | direct read |
| No replay transport exists | T1 | grep for `ReplayTransport` → no matches | executed grep |
| Step results carry the response a replay needs | T1 | `StepResult.php:21-34` | direct read |
| A deterministic fake transport is already a supported pattern | T1 | `FakePsr18Client`, `FakerOpenApiExecutor` | direct read |