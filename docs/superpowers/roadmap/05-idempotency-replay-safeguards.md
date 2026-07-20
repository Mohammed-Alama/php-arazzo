# 05. Idempotency & Replay Safeguards

**Category:** Backend — Enterprise Reliability & Scale
**Phase:** 1 — Core reliability primitives
**Depends on:** [03 — Native Asynchronous Control Flow](03-native-async-control-flow.md)
**Status:** Not started — needs brainstorming

## Description

Auto-generates and injects `Idempotency-Key` headers based on unique workflow and step UUIDs
to ensure safe manual replays from network timeouts.
