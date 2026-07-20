# 03. Native Asynchronous Control Flow

**Category:** Backend — Core Orchestration Engine
**Phase:** 0 — Engine foundation
**Depends on:** [02 — CQRS & Event-Sourced Persistence](02-cqrs-event-sourced-persistence.md)
**Status:** Not started — needs brainstorming

**Existing code:** `Engine`, `StepExecutionWorker` (with choreography wiring added in the
queue-integration work), `QueueDriverInterface`/`LaravelQueueDriver`, `LockManagerInterface`
already exist and are partially wired — see `CHANGELOG.md`'s queue-integration entry and its
"Known gaps" list. Not built at all yet: parsing `retryAfter`/`successCriteria` from the
Arazzo YAML into actual sleep/re-queue behavior, and webhook suspension. Also carries two
known correctness gaps to resolve as part of this work: double-dispatch prevention only holds
for linear step chains (not diamond/fan-in DAGs), and the definition registry is process-local
(see [02](02-cqrs-event-sourced-persistence.md)).

## Description

Built-in engine logic to parse `retryAfter` and `successCriteria` directly from the Arazzo
YAML. It autonomously manages sleep/re-queue cycles and webhook suspensions without relying on
recursive queue jobs.
