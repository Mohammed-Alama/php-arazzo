# 16. The Event Ledger

**Category:** UI — Core Execution & Observability Views
**Phase:** 5 — UI: core execution & observability
**Depends on:** [02 — CQRS & Event-Sourced Persistence](02-cqrs-event-sourced-persistence.md)
**Status:** Not started — needs brainstorming

## Description

A chronological, paginated table reading directly from the partitioned PostgreSQL event
store. Displays every HTTP request, status code, latency, and timestamp.
