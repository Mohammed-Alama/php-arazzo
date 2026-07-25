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

**1.1.0 delta:** compensation-via-sub-workflow is now spec-native, not something to bolt on —
Failure Action Objects gained full `workflowId`+`parameters` (Parameter Object array, `in`
must not be set on these). `Engine::evaluate()` needs a call-stack for this: push/pop a
`WorkflowContext` per sub-workflow invocation seeded only from the resolved `parameters` (not
inherited parent scope), plus cycle detection across workflow boundaries next to
`DependencyAnalyzer`'s existing step-level cycle check. See the `cancelOrderSaga` workflow in
`tests/fixtures/parser/arazzo-1.1-cross-protocol-saga.yaml` for the target shape.
