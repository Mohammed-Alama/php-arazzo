# Signals & Queries

Category: **exec** · Phase: **2-orchestration** · Tier: **OSS**
Depends on: shipped `CQRS & Event-Sourced Persistence`, shipped `Native Asynchronous Control Flow`

## Problem

The only way to affect a running execution from outside is the AsyncAPI correlation-resume
path (`WebhookResumeController` → `ResumeCorrelationJob`), which is scoped to a single step's
protocol binding and consumed once. There is no general mechanism to:

- poke a *running* execution with an external event at an arbitrary point (human approval,
  manual override, cancellation) — the workflow can't declare "pause here until told to
  continue"
- read an execution's live in-memory state without affecting it (current step, accumulated
  outputs, a custom flag) without querying the event ledger directly and reconstructing state
  yourself

`durable-workflow/workflow`'s `SignalMethod`/`QueryMethod`/`UpdateMethod` trio (plus its
replay-safe inbox/outbox) is the reference shape for this. Human-in-the-loop approval steps
are one of the top reasons teams reach for a durable-execution tool at all, and Arazzo
documents currently have no vocabulary for "wait for an external signal."

## Feature

Extend the Arazzo document vocabulary with an `x-await-signal` step-level extension (OSS
custom extension, not spec-breaking — Arazzo already allows `x-*` fields) that pauses
execution until a named signal arrives:

```yaml
steps:
  - stepId: awaitApproval
    x-await-signal:
      name: approval
      timeout: null   # or an ISO-8601 duration; see exec-48-durable-timers
```

```php
interface SignalRegistryInterface
{
    public function send(string $executionId, string $signalName, array $payload = []): void;
}

interface QueryHandlerInterface
{
    public function query(string $executionId, string $queryName): mixed;
}
```

- `SignalRegistryInterface` default impl persists signal delivery as an event on the existing
  `EventLedgerInterface` (`SignalReceived`), so replay picks it up deterministically — same
  event-sourcing mechanism the engine already uses for step outcomes, no new persistence
  primitive needed.
- An execution paused on `x-await-signal` resumes via the same job-dispatch path
  `ResumeCorrelationJob` already uses (rename/generalize to `ResumeAwaitingExecutionJob` or
  extend the existing one — avoid duplicating the resume machinery that already exists for
  AsyncAPI correlation).
- Built-in inbox semantics: unread signals for a given `name` queue up if the workflow hasn't
  reached the matching `x-await-signal` step yet; consumed exactly once on replay (mirrors
  durable-workflow's inbox, prevents double-processing on rebuild).
- Queries read `WorkflowContext` (or the reconstructed hot state in `RedisHotStateStore`)
  directly — no event is appended, since queries must not affect execution or replay.
- Both exposed via the Laravel bridge as artisan-friendly facade methods and, longer term,
  the webhook auth strategies already shipped for AsyncAPI resume (token/HMAC) should cover
  signal delivery too rather than inventing a second auth story.

## Acceptance

- A workflow with `x-await-signal` genuinely suspends (existing job exits, no busy-wait) and
  resumes only on matching signal delivery.
- Signals delivered before the workflow reaches the corresponding step are queued and
  consumed on arrival (inbox semantics), verified by a replay test.
- Query calls never append to the event ledger and never change execution status.
- Existing AsyncAPI correlation-resume behavior unchanged (this is additive, not a
  replacement — `WebhookResumeController` keeps working as-is for protocol-bound resume).
- New validator rule rejects `x-await-signal` on documents where the step also declares an
  `operationId`/`operationPath` (a step is either an API call or a signal wait, not both).

## Out of scope

- Updates (query+signal combined) — natural follow-on once both primitives exist, but adds
  its own consistency questions (does the mutation happen before or after the read is
  returned to the caller?) worth its own stub once signals/queries have shipped and proven
  the resume plumbing.
- General-purpose webhook auth strategy overhaul — tracked separately if signal delivery
  needs public exposure beyond what AsyncAPI resume auth already covers.
