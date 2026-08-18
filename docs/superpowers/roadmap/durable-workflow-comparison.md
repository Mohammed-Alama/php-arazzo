# Durable Workflow Comparison & Gap Analysis

**Status:** Research note
**Created:** 2026-08-07
**Related:** `docs/superpowers/roadmap/ROADMAP.md`

This document compares [Durable Workflow](https://durable-workflow.com) (a Laravel-native workflow engine using PHP generators/yield) against our `laravel-arrazo` Arazzo specification executor. The goal is to identify missing orchestration primitives in our engine and validate our roadmap.

## Paradigm Shift: Imperative vs. Declarative

- **Durable Workflow** uses an imperative, code-first approach. Workflows are PHP classes, steps are PHP methods or activities, and control flow is written in raw PHP using `yield`.
- **Laravel Arazzo** uses a declarative, standard-first approach. Workflows are defined in Arazzo YAML/JSON and chained using `dependsOn` and `$steps` references.

Because Arazzo does not execute arbitrary PHP code (it orchestrates API calls via `operationId`), we must implement orchestration primitives (like pauses, loops, and external signals) as Arazzo step extensions rather than native PHP functions.

## Feature Mapping & Roadmap Status

| Durable Workflow Feature | Laravel Arazzo Equivalent | Roadmap Status |
| :--- | :--- | :--- |
| **Activities** | Arazzo Steps (OpenAPI operations) | Shipped |
| **Data Passing** | Variable Context (`$inputs`, `$steps`, `$response`) | Shipped |
| **Timers / Sleep** | `x-wait` step extension | Planned: `exec-48-durable-timers` |
| **Signals (External Input)** | `x-await-signal` step extension | Planned: `exec-47-signals-and-queries` |
| **Queries (State Reads)** | `QueryHandlerInterface` / API reads | Planned: `exec-47-signals-and-queries` |
| **Signal + Timer (Race)** | Timers racing against signals | Planned: `exec-48-durable-timers` |
| **Heartbeats** | `HeartbeatInterface` (step-level liveness) | Planned: `rel-49-heartbeat-interface` |
| **Child Workflows** | Sub-workflow composition | Planned: `core-34-arazzo-1.1.0-spec` |
| **Side Effects** | Not applicable (Arazzo executes API calls) / Idempotency Store | Planned: `rel-42-idempotency-store-interface` |

## Identified Gaps & Missing Specs

While our `ROADMAP.md` correctly predicts most advanced orchestration primitives required, there are a few nuanced features from Durable Workflow that we need to account for:

1. **Inbox / Outbox (Replay-Safe Messaging)**
   Durable Workflow provides an Inbox (for consuming multiple signals safely across replays) and an Outbox (for producing multiple outgoing messages/queries safely).
   *Action:* We need to ensure that `exec-47-signals-and-queries` incorporates an event log or Inbox-style consumption to prevent signal-loss during replay when a workflow is hibernated and resumed multiple times.
   
2. **Updates (Mutating Queries)**
   Durable Workflow provides `#[UpdateMethod]` to synchronously mutate state and return a query result.
   *Action:* In Arazzo, workflow state is immutable apart from step outputs. An equivalent would be allowing an external signal to provide a payload that gets appended to a `$signals` context variable. We must expand `exec-47` to include payload parsing.

3. **Looping Control Flow**
   Durable Workflow uses native PHP `while` loops to repeat activities. Arazzo currently has basic `onFailure.retry` and `goto`, but lacks a native iterative loop construct (e.g., repeating a step until a condition is met, independent of failure).
   *Action:* We should consider adding a `core-50-iteration-constructs` stub for `x-loop` or `x-until` extensions for pagination or iterative processing.

## Conclusion

Our engine is tracking well towards parity with code-first durable execution engines. The primary focus should be implementing `exec-47` and `exec-48`, as durable timers and external signals are the hallmarks of long-running workflows. Arazzo's declarative nature requires us to handle looping and messaging carefully via `x-` extensions.
