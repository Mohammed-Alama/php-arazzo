# Durable Timers

Category: **exec** · Phase: **2-orchestration** · Tier: **OSS**
Depends on: shipped `Native Asynchronous Control Flow`, `exec-47-signals-and-queries` (for the
signal+timeout race case)

## Problem

There's no way to express "pause this execution for N minutes/days, independent of any API
call, and resume even if the process/server restarts in between." The `StepOutcomeHandler`
retry backoff is the closest thing that exists, but it's tied to a *failed* step retrying,
not a deliberate pause — there's no way to model dunning flows, trial-expiration workflows,
or "wait 24h then send a follow-up" as a first-class wait, without faking it as a step that
always "fails" until a clock check passes (which pollutes the event ledger with fake
failures and breaks idempotency assumptions).

`durable-workflow/workflow`'s `timer()` / `days()`/`hours()` helpers and `awaitWithTimeout()`
(race a signal against a timeout) are the reference shape.

## Feature

A step-level `x-wait` extension:

```yaml
steps:
  - stepId: waitBeforeFollowUp
    x-wait:
      duration: P1D   # ISO-8601 duration, matches OpenAPI/Arazzo's existing duration conventions
```

```php
interface DurableTimerInterface
{
    public function schedule(string $executionId, string $stepId, \DateInterval $duration): void;
}
```

- Timer scheduling appends a `TimerScheduled` event to the existing `EventLedgerInterface`
  (fires-at timestamp computed via `Workflow::now()`-equivalent — i.e. resolved once at
  schedule time and stored, never recomputed on replay, matching the determinism rule
  durable-workflow documents for the same reason).
- Resume is queue-native: schedule the resume job with the queue driver's own delay support
  (`LaravelQueueDriver` already wraps this) rather than building a separate polling
  scheduler. For queue backends without long native delays (matches durable-workflow's own
  SQS caveat), chain shorter delayed dispatches transparently — same problem they solved,
  worth reusing their approach rather than re-deriving it.
- Combine with `exec-47`'s signal wait for a race primitive: `x-wait` + `x-await-signal` on
  the same step resolves to "whichever happens first," mirroring `awaitWithTimeout()`.
- `TimerFired` event distinguishes "timeout won the race" from "signal won the race" in the
  ledger, so `obs-16-event-ledger` (when built) can render it meaningfully.

## Acceptance

- A workflow with `x-wait: { duration: PT5M }` genuinely suspends (job exits, no busy-wait or
  polling loop) and resumes automatically after the duration elapses, including across a
  worker restart.
- Timer fire time is computed once at schedule time and persisted; replay never recomputes it
  from wall-clock time (verified by a replay test that fast-forwards a fake clock).
- Combined `x-wait` + `x-await-signal` on one step: whichever fires first wins, the other is
  a no-op if it arrives after, and the ledger records which one resolved the wait.
- Long durations (days/weeks) work correctly on queue drivers with short native delay limits
  (SQS-style), via chained delayed dispatch — parity with durable-workflow's documented
  behavior, not just "works on Redis."

## Out of scope

- A generic cron/schedule trigger to *start* new workflows on a recurring basis — this stub
  is about pausing an already-running execution, not scheduling new ones.
- `continueAsNew`-style history compaction for workflows that loop many times — Arazzo
  documents are bounded orchestrations, not long-lived loops; if a real use case for
  unbounded looping shows up, it gets its own stub rather than being bundled here.
