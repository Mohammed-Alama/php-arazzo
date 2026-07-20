# 23. Error Triage Board

**Category:** UI — System Health & Scaling Operations
**Phase:** 7 — UI: system health & scaling ops
**Depends on:** [06 — SLA Monitors & Dead Letter Workflows](06-sla-monitors-dead-letter-workflows.md), [16 — The Event Ledger](16-event-ledger.md)
**Status:** Not started — needs brainstorming

## Description

A Kanban-style interface for Dead Letter Queue management. Groups failed workflows by root
cause (e.g., 504 Gateway Timeout on Stripe API), allowing operators to bulk-retry thousands of
runs at once.
