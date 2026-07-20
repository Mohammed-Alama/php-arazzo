# 24. Golden Path Overlay

**Category:** UI — System Health & Scaling Operations
**Phase:** 7 — UI: system health & scaling ops
**Depends on:** [15 — The Graph Explorer](15-graph-explorer.md), [02 — CQRS & Event-Sourced Persistence](02-cqrs-event-sourced-persistence.md) (partitioned historical data)
**Status:** Not started — needs brainstorming

## Description

**The Problem:** Over time, as workflows execute thousands of times, certain branches in
complex conditional logic are rarely taken, while others represent the vast majority of
traffic.

**The Feature:** Execution frequency visualization. By analyzing historical data from the
PostgreSQL ledger, the UI overlays line thickness on the DAG edges based on traffic volume.
The most common execution path (the "Golden Path") appears as a thick, prominent line, while
edge cases appear as thin lines. This instantly communicates the real-world behavior of the
workflow to the developer.
