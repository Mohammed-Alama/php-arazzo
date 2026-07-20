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

**1.1.0 delta:** the design spec (`docs/superpowers/specs/2026-07-20-zero-code-data-pipelining-design.md`)
and plan (`docs/superpowers/plans/2026-07-20-zero-code-data-pipelining.md`) were written before
Arazzo 1.1.0 was confirmed real, and both made decisions that are now stale — see the addendum
at the top of each. Short version: bare-`$.`-prefix JSONPath sniffing in `extractOutputs` and
the hardcoded `XPath => throw UnsupportedCriterionTypeException` in `evaluateSuccessCriteria`
were reasonable for 1.0.0-only scope but should be superseded by a real Selector Object
(`context`/`selector`/pinned-version `type`) rather than extended further — don't add more
prefix-sniffing heuristics on top of what's there.
