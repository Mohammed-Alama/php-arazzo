# 07. Automated Saga Pattern (Compensation Engine)

**Category:** Backend — Enterprise Reliability & Scale
**Phase:** 2 — Advanced orchestration
**Depends on:** [03 — Native Asynchronous Control Flow](03-native-async-control-flow.md), [05 — Idempotency & Replay Safeguards](05-idempotency-replay-safeguards.md)
**Status:** Not started — needs brainstorming

## Description

Automatically routes backward through the execution graph upon failure, executing defined
compensation APIs to cleanly roll back distributed transactions. This is critical for
orchestrating multi-step financial flows, such as automated settlement and penalty
frameworks.
