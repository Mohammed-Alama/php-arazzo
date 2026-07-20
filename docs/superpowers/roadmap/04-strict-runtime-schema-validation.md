# 04. Strict Runtime Schema Validation

**Category:** Backend — Core Orchestration Engine
**Phase:** 1 — Core reliability primitives
**Depends on:** [01 — Zero-Code Data Pipelining](01-zero-code-data-pipelining.md)
**Status:** Not started — needs brainstorming

## Description

An optional fail-fast validation layer that evaluates incoming API payloads against the
OpenAPI schema prior to processing Arazzo success criteria, avoiding type-mismatch fatal
errors deep in the execution graph.

**1.1.0 delta:** the Selector Object's Expression Type Object (`{type, version}`) is itself a
validation surface — `xpath-30`/`xpath-31` have no PHP stdlib support (`DOMXPath` is XPath 1.0
only), so a pinned version this engine can't execute should fail validation at parse time, not
surface as a confusing runtime error three layers down. Add a validation rule rejecting
unsupported `(type, version)` pairs early, alongside whatever OpenAPI-schema-casting rules this
item already covers.
