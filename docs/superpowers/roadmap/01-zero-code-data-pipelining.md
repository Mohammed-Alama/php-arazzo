# 01. Zero-Code Data Pipelining

**Category:** Backend — Core Orchestration Engine
**Phase:** 0 — Engine foundation
**Depends on:** None (foundation)
**Status:** Not started — needs brainstorming

**Existing code:** `TypeCaster`, `JsonPathEvaluator`, `ArazzoExpressionResolver` already exist
(see `CHANGELOG.md`, "Added — not yet wired into the runtime") but `ArazzoExpressionResolver`
is still an MVP stub (hardcoded `GET`, no OpenAPI operation resolution, no query/body params,
no success-criteria evaluation) and none of the three are used by the live `StepExecutor`
(which uses `ExpressionEvaluator`/`JsonPointer` instead). Brainstorm from "how do we finish
and wire this existing chain," not from scratch — including deciding whether it *replaces*
`ExpressionEvaluator`/`JsonPointer` or the two mechanisms need to be reconciled.

## Description

A native JSONPath resolver utilizing an immutable `WorkflowContext` DTO. This prevents state
mutation between steps and supports advanced data extraction and strict type casting before
payloads reach the HTTP client.
