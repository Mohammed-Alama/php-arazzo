# 08. Dynamic Fan-Out / Fan-In

**Category:** Backend — Enterprise Reliability & Scale
**Phase:** 2 — Advanced orchestration
**Depends on:** [03 — Native Asynchronous Control Flow](03-native-async-control-flow.md)
**Status:** Not started — needs brainstorming

Note: the current choreography wiring's double-dispatch prevention (see
[03](03-native-async-control-flow.md)) only holds for linear chains, not fan-out/fan-in — this
feature is exactly the case that gap needs fixed for. Don't design this against the current
worker's dispatch logic as-is; the fix belongs together with this feature, not after it.

## Description

Implements a Map-Reduce architecture. It parses arrays from the context, spawns concurrent
child workflow graphs across local queue workers, and aggregates the results back into a
single context array.
