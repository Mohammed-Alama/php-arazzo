# 15. The Graph Explorer

**Category:** UI — Core Execution & Observability Views
**Phase:** 5 — UI: core execution & observability
**Depends on:** [02 — CQRS & Event-Sourced Persistence](02-cqrs-event-sourced-persistence.md)
**Status:** Not started — needs brainstorming

**Existing code:** the shipped React Flow workflow-builder UI (`resources/js/arazzo-ui.jsx`,
see `CHANGELOG.md`) is a *different* feature — it's for constructing a workflow by dragging
OpenAPI endpoints, not for observing a live/past execution. It shares the `reactflow`
dependency and general canvas approach, which is reusable groundwork, but this is new build,
not a refactor of that component.

## Description

A visual Directed Acyclic Graph (DAG) representation of the Arazzo YAML file. It highlights
the active execution path, showing branch logic and coloring nodes based on status (e.g.,
green for success, red for failure, yellow for running/waiting).
