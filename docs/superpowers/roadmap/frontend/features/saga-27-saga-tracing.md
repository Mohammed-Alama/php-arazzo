# 27. Visual Saga Tracing

**Category:** UI — Developer Experience (DX) & Ecosystem Integration
**Phase:** 8 — UI: DX & ecosystem integration
**Depends on:** [07 — Automated Saga Pattern (Compensation Engine)](07-automated-saga-compensation-engine.md), [15 — The Graph Explorer](15-graph-explorer.md)
**Status:** Not started — needs brainstorming

## Description

**The Problem:** When a distributed transaction fails and a compensation flow (Saga pattern)
triggers, understanding the relationship between the failed forward action and the successful
backward rollback action is incredibly confusing in a linear log.

**The Feature:** A dual-state graph rendering. When a workflow enters compensation mode, the
UI animates the transition. The failed node pulses red, and the UI dynamically draws new
edges mapping the backward path. The operator can clearly see "Step C failed -> System
triggered Step B Rollback -> System triggered Step A Rollback," with distinct visual styles
(e.g., dotted lines) denoting compensation paths.
