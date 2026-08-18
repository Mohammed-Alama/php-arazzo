# 06. SLA Monitors & Dead Letter Workflows

**Category:** Backend — Enterprise Reliability & Scale
**Phase:** 1 — Core reliability primitives
**Depends on:** [02 — CQRS & Event-Sourced Persistence](02-cqrs-event-sourced-persistence.md), [03 — Native Asynchronous Control Flow](03-native-async-control-flow.md), [rel-49-heartbeat-interface](rel-49-heartbeat-interface.md)
**Status:** Not started — needs brainstorming

## Description

A monitor that watches Time-to-Live (TTL) policies and `rel-49-heartbeat-interface` liveness
signals together, automatically terminating stalled workflows and routing them to a specific
Dead Letter fallback state as soon as a step misses its heartbeat window — not just after the
worst-case TTL expires.
