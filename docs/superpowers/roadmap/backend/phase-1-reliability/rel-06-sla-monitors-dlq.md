# 06. SLA Monitors & Dead Letter Workflows

**Category:** Backend — Enterprise Reliability & Scale
**Phase:** 1 — Core reliability primitives
**Depends on:** [02 — CQRS & Event-Sourced Persistence](02-cqrs-event-sourced-persistence.md), [03 — Native Asynchronous Control Flow](03-native-async-control-flow.md)
**Status:** Not started — needs brainstorming

## Description

A heartbeat scheduler that monitors Time-to-Live (TTL) policies, automatically terminating
stalled workflows and routing them to a specific Dead Letter fallback state.
