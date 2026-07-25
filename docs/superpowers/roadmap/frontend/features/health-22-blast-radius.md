# 22. Blast Radius Analyzer (Heatmap)

**Category:** UI — System Health & Scaling Operations
**Phase:** 7 — UI: system health & scaling ops
**Depends on:** [16 — The Event Ledger](16-event-ledger.md)
**Status:** Not started — needs brainstorming

## Description

**The Problem:** An upstream SaaS API (e.g., Stripe or Twilio) goes down, causing hundreds of
concurrent workflows to fail simultaneously. Operators need to know the extent of the damage
instantly.

**The Feature:** A global system health overlay. The dashboard aggregates failures by
endpoint or error code. A visual heatmap shows which specific nodes across all active
workflows are currently bottlenecking or failing. Clicking a "red zone" node instantly filters
the view to show every affected workflow ID, allowing for bulk "retry" or "suspend" actions.
