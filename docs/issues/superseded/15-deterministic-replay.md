# Draft issue 15 — Deterministic replay from a trace

- **Suggested title:** `[Feature] `arazzo replay` — re-execute a workflow from a recorded trace`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `cli`, `runner`, `protocol-http`, `runtime`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` new command; `packages/runner` execution; `packages/protocol-http` transport seam; `packages/runtime` state.
- **Arazzo spec version:** independent of spec version.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core CLI.
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P2 — depends on traces (draft 14), but unlocks deterministic debugging and regression tests.

### 📝 2. Problem Statement & Intent

`CliRunner` has a `resume()` path that reloads persisted state and continues from
the first non-succeeded step (`packages/cli/src/Console/Cli/CliRunner.php:138-170`).
That is **resumption of a live run**, not replay. It still contacts the real
transport for any step it re-executes, and it has no way to substitute recorded
responses.

A recorded trace (draft 14) contains the request and response for every step.
Nothing consumes it. As a result:

- a failure that depends on a specific server response cannot be reproduced
  offline;
- a flaky endpoint makes regression tests nondeterministic;
- a production incident cannot be re-run against the exact bytes that caused it
  without a live, possibly mutated, environment.

Expected: `arazzo replay <trace.json>` re-executes the workflow with a transport
that serves the recorded responses in order, without sending network requests.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `arazzo replay <trace.json>` reads a `trace.v1` artifact and executes the
  recorded workflow.
- A `ReplayTransport` implements the HTTP seam and returns recorded responses
  keyed by `(stepId, attempt)`; an unexpected request fails loudly with a stable
  error instead of falling through to the network.
- `--spec <path>` overrides `run.documentPath`; `--workflow-id <id>` overrides
  the recorded workflow.
- Every replayed step must produce the same status, outputs and criteria result
  as the trace. A mismatch is a replay failure with a diff.
- Replay accepts only records that contain a response; a trace captured with
  `--dry-run` is rejected as non-replayable.
- Replay never sends network traffic, even if a step is not present in the trace.
- Support `--json` output using the `run-report.v1` shape (draft 12).

**Non-goals**

- Time-travel debugging or breakpoints (a DAP debugger is out of scope).
- Replaying non-HTTP protocols in the first iteration; HTTP/OpenAPI only.
- Editing recorded responses.

**Prior art in this repository**

- `CliRunner::resume()` proves state-driven continuation exists
  (`CliRunner.php:138-170`).
- `StepResult` carries the response that replay needs.
- `FakePsr18Client` and the conformance `FakerOpenApiExecutor` prove a
  deterministic transport seam is already a supported pattern; neither is a
  production replay transport.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Re-execute a workflow from a recorded trace without sending network requests, enabling deterministic debugging and regression tests.

**Architectural Boundary:** Bounded context = replay engine + transport seam. Packages: `packages/cli` (new command), `packages/runner` (execution), `packages/protocol-http` (transport seam), `packages/runtime` (state). Core replay engine is draft 48; this draft is the CLI port. Depends on traces (draft 14 core).

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Replay serves recorded responses from memory; no network I/O; matches responses by `(stepId, attempt)` in O(1).
- [ ] **Flexible:** `--spec <path>` and `--workflow-id <id>` override recorded values; `ReplayTransport` implements PSR-18 `ClientInterface` (swappable seam).
- [ ] **Maintainable:** Single-responsibility replay transport; stable errors (`replay.missing_response`, `replay.not_replayable`); typed failures.
- [ ] **Operable:** `arazzo replay <trace.json>` runs without outbound requests; `--json` output using `run-report.v1` shape; rollback is removing the command.
- [ ] **Reliable:** Every replayed step produces the same status/outputs/criteria as the trace; mismatch is a replay failure with diff; exits non-zero on any mismatch.
- [ ] **Safe:** No network traffic sent during replay; traces from `--dry-run` rejected as non-replayable; no side effects beyond report generation.
- [ ] **Secure:** No new authorization surface; replay is read-only against recorded data.
- [ ] **Suitable:** Unlocks deterministic debugging and regression testing; depends on traces (draft 14 core).
- [ ] **Usable:** `arazzo replay <trace.json>` with `--spec`/`--workflow-id` overrides; `--json` output for machine consumption.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `arazzo replay <trace.json>` runs without outbound requests**
- **Given** a recorded `trace.v1` artifact (`trace-id: 01H8XABC123`) containing 4 executed steps with recorded HTTP request/response frames; `ReplayTransport` (draft 48 core) implements PSR-18 `ClientInterface`; `arazzo replay trace-01H8XABC123.json --mode=strict` is invoked; no real network is available.
- **When** `php bin/console arazzo replay trace-01H8XABC123.json --mode=strict` executes.
- **Then** the workflow re-executes using `ReplayTransport` which returns recorded responses keyed by `(stepId, attempt)`; zero outbound network requests are sent; `ReplayTransport` never falls through to the network; the command completes using only recorded data.

**Scenario 2: Responses matched by step id and attempt**
- **Given** a `trace.v1` artifact where step `charge-payment` has 3 attempts (attempt 1 failed with 503, attempt 2 failed with 503, attempt 3 succeeded with 200); each attempt has a distinct recorded response frame.
- **When** `ReplayTransport` processes step `charge-payment` across its 3 attempts.
- **Then** attempt 1 receives the recorded 503 response; attempt 2 receives the recorded 503 response; attempt 3 receives the recorded 200 response; responses are matched by `(stepId: "charge-payment", attempt: N)` in O(1) lookup.

**Scenario 3: Step missing from trace produces stable error**
- **Given** a `trace.v1` artifact containing steps `step-1` and `step-2` only; the workflow being replayed has a third step `step-3` that is not present in the trace.
- **When** replay reaches `step-3` and `ReplayTransport` looks up `(stepId: "step-3", attempt: 1)`.
- **Then** a stable error `replay.missing_response` is returned; no network call is made; the error message identifies the missing step ID and attempt; `ValidationResult::isValid()` returns `false`.

**Scenario 4: Failed run replays the same failure**
- **Given** a `trace.v1` artifact captured from a failed run where step `validate-payment` (step index 1) failed with criterion `$statusCode == 200` evaluating to `false` (actual status 402); `arazzo replay trace-failed.json` is invoked.
- **When** replay executes the recorded workflow.
- **Then** the failure replays at step `validate-payment` (step index 1); the same criterion outcome (`not-met`) is produced; the criterion reason (`Expected statusCode 200, got 402`) matches the original; `ReplayTransportTest` verifies the replayed outputs match the trace.

**Scenario 5: Trace from `--dry-run` is rejected**
- **Given** a `trace.v1` artifact captured under `--dry-run` mode (no response frames recorded, only request plans); `arazzo replay trace-dryrun.json` is invoked.
- **When** replay attempts to load and validate the trace artifact.
- **Then** the trace is rejected with error `replay.not_replayable`; `ErrorCollector` records the error; `ValidationResult::isValid()` returns `false`; no execution is attempted.

**Scenario 6: Outputs match the trace exactly**
- **Given** a recorded `trace.v1` artifact where workflow outputs are `{ "orderId": "ORD-12345", "total": 99.99 }`; `arazzo replay trace.json` is invoked.
- **When** replay completes and outputs are compared against the trace.
- **Then** replayed outputs match the trace exactly (`orderId: "ORD-12345"`, `total: 99.99`); any mismatch causes a non-zero exit code; `ReplayTransportTest` runs once live (fake transport), records a trace, then replays it and asserts output equality.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `arazzo replay <trace.json>` runs the recorded workflow without any outbound request
- [ ] The replay transport matches responses by step id and attempt
- [ ] A step missing from the trace produces a stable `replay.missing_response` error, not a network call
- [ ] A trace from a failed run replays the failure at the same step with the same criterion outcome
- [ ] A trace captured under `--dry-run` is rejected with `replay.not_replayable`
- [ ] Outputs match the trace exactly; the command exits non-zero on any mismatch
- [ ] `--spec` and `--workflow-id` override the recorded values and are reflected in the report
- [ ] `--json` output using the `run-report.v1` shape (draft 12 core)
- [ ] Replay covered by a fixture: run once live (fake transport), record a trace, then replay it
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] No telemetry/logging changes needed beyond existing error handling
- [ ] Core engine (`ReplayTransport`) is draft 48; this draft is the CLI port only

### 🧬 10. Source Grounding & Code Location

- `packages/cli/src/Console/Cli/CliRunner.php:138-170` — resume is not replay.
- `packages/cli/src/Console/Command/RunCommand.php` — no `--trace`/replay equivalent.
- `packages/protocol-http/src/Execution/Interfaces/OpenApiExecutorInterface.php` — the seam a replay transport must satisfy.
- `packages/runner/src/Execution/Data/StepResult.php:21-34` — response data recorded per step.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/cli/src/Console/Cli/CliRunner.php#L138-L170"><code>CliRunner.php:138-170</code></a> @ <code>ea8c55a</code></summary>

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

- **Public API impact:** new command; a new internal replay transport.
- **Blast radius:** `packages/cli`, `packages/runner` transport seams; no change to live execution when replay is not used.
- **Severity:** medium — debugging and deterministic regression testing.
- **Rollback:** remove the command; traces remain useful on their own.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `resume()` continues a live run, not a replay | T1 | `CliRunner.php:138-170` | direct read |
| No replay command or transport exists | T1 | `grep -rIn -i "replay" packages/cli/src packages/runner/src` → only a comment in `ExecutionState` | executed |
| Step results carry the response a replay needs | T1 | `StepResult.php:21-34` | direct read |
| A deterministic fake transport is already a supported pattern | T1 | `FakePsr18Client`, `FakerOpenApiExecutor` | direct read |
| Replay fidelity requires matching by step and attempt | T2 | retries produce multiple responses per step | inferred |
