# `HeartbeatInterface`

Category: **rel** · Phase: **1-reliability** · Tier: **OSS** (interface + local impl) + **Pro** (cluster-wide impl)
Depends on: shipped `EventLedgerInterface`, shipped `Native Asynchronous Control Flow`
Feeds: [rel-06-sla-monitors-dlq](rel-06-sla-monitors-dlq.md) — the DLQ monitor needs a liveness
signal to distinguish "still working" from "dead," this stub is that signal.

## Problem

A long-running step (LLM call, file processing, slow third-party sync) currently has exactly
one failure signal: a hard timeout. That forces a bad tradeoff — set the timeout short and
legitimately-slow-but-healthy work gets killed and retried needlessly; set it long and a
genuinely stuck/crashed worker sits undetected for the full timeout window before `rel-06`'s
SLA monitor can even notice something's wrong.

`durable-workflow/workflow`'s activity heartbeat pattern solves this by having the executing
code report liveness at intervals *during* execution, independent of the final
success/failure outcome. A monitor watching for missed heartbeats can distinguish "no
heartbeat because it's dead" from "no heartbeat yet because it's still working," and act on
the former immediately rather than waiting out a worst-case timeout.

## Feature

```php
interface HeartbeatInterface
{
    public function beat(string $executionId, string $stepId, array $detail = []): void;
    public function lastBeat(string $executionId, string $stepId): ?HeartbeatRecord;
}

final readonly class HeartbeatRecord
{
    public \DateTimeImmutable $at;
    public array $detail;   // free-form progress info, e.g. ['processed' => 4200, 'total' => 10000]
}
```

- `beat()` is called from inside a step executor's callback during long-running work (e.g.
  an `HttpStepExecutor` streaming a large response, or a custom step handler processing a
  batch) — appends a `HeartbeatReceived` event to the existing `EventLedgerInterface` rather
  than a separate store, keeping heartbeat history part of the same replayable event stream
  steps already write to.
- Per-step `x-heartbeat-timeout` extension declares the max allowed gap between beats before
  a step is considered dead:

  ```yaml
  steps:
    - stepId: processLargeExport
      x-heartbeat-timeout: PT30S
  ```

- Core ships:
  - `NullHeartbeat` — no-op, current behavior (opt-in feature, zero cost when unused).
  - `LocalHeartbeat` — process-local, appends directly to the event ledger, fine for
    single-worker deployments and tests.
- Pro (`arazzo-pro-persistence`) ships:
  - `RedisHeartbeat` — cluster-wide, so a monitor process on a different worker can detect a
    missed beat without querying the primary DB on every poll.
- `rel-06`'s SLA/DLQ monitor becomes a consumer of `lastBeat()`: a step past its
  `x-heartbeat-timeout` with no fresh beat routes to the Dead Letter fallback immediately,
  instead of waiting for the step's own outer timeout.

## Acceptance

- A step calling `beat()` periodically during long-running work never triggers its own
  `x-heartbeat-timeout`, even if total execution time exceeds that timeout — only *gaps*
  between beats matter, not total duration.
- A step that stops beating (simulated worker crash mid-execution) is detected as dead within
  one `x-heartbeat-timeout` window, verified by a fixture that kills the worker process
  mid-step and asserts the monitor flags it.
- Heartbeat events are ordinary entries in the event ledger — replay reconstructs heartbeat
  history the same way it reconstructs step outcomes, no separate persistence path.
- Steps with no `x-heartbeat-timeout` declared behave exactly as today (opt-in, zero
  behavioral change for existing documents).
- `NullHeartbeat` imposes no measurable overhead when heartbeating isn't configured.

## Out of scope

- The DLQ routing/termination logic itself — that's `rel-06`'s job; this stub only provides
  the liveness signal `rel-06` consumes.
- Automatic heartbeat injection into arbitrary third-party HTTP calls (you can't `beat()`
  from inside code you don't control) — this only helps steps whose executor can call back
  periodically, e.g. streaming responses or custom batch-processing step handlers.
