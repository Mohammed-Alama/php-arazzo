# Laravel Arazzo — Feature Roadmap

Master index of proposed features, deduplicated from three source dumps and split into one
spec stub per feature (`docs/superpowers/roadmap/NN-slug.md`). Each stub is a **brainstorming
seed**, not a design spec — run `superpowers:brainstorming` on a stub to turn it into a real
spec (which then lives in `docs/superpowers/specs/`) and plan (`docs/superpowers/plans/`),
same as every already-shipped feature in `CHANGELOG.md`.

Files are numbered in the order this roadmap recommends tackling them (roughly: dependency
order, foundation before the features built on top of it). The number is not a priority
ranking beyond that — reorder freely if priorities change, just keep filenames matching their
position so the list stays self-documenting.

## Deduplication notes

Three source documents were merged:

- **Doc A** ("core backend features") and **Doc B**'s sections 5–8 ("...Backend") are the
  same 14 features, verbatim. Only counted once.
- **Doc C** ("expanded UI feature deep-dives") re-describes 6 features already listed tersely
  in Doc B's UI sections, with more detail (problem statement + feature description). Where
  that happened, the richer Doc C text was kept as the stub's canonical description instead
  of Doc B's one-liner: Interactive Time-Travel Debugger, JSONPath Visual Diffing, Webhook
  Payload Interception, Blast Radius Analyzer, Visual Saga Tracing, Golden Path Overlay.
- Net result: **29 unique features** (14 backend, 15 UI), not the ~35 the raw line count
  suggested.

## Overlap with what's already built

Three of the "Core Orchestration Engine" backend features are **not greenfield** — they
already have real (unit-tested but unwired) scaffolding in the codebase, per `CHANGELOG.md`'s
"Added — not yet wired into the runtime" section. Brainstorming for these should start from
"how do we wire and complete the existing code," not "how do we design this from scratch":

- [01 — Zero-Code Data Pipelining](01-zero-code-data-pipelining.md) — `TypeCaster`,
  `JsonPathEvaluator`, `ArazzoExpressionResolver` (stub) exist.
- [02 — CQRS & Event-Sourced Persistence](02-cqrs-event-sourced-persistence.md) —
  `RedisHotStateStore`, `DatabaseEventLedger`, `InMemoryDefinitionRegistry` exist.
- [03 — Native Asynchronous Control Flow](03-native-async-control-flow.md) — `Engine`,
  `StepExecutionWorker` choreography, `QueueDriverInterface`/`LaravelQueueDriver`,
  `LockManagerInterface` exist and are partially wired (see queue-integration entry in
  `CHANGELOG.md` and its known double-dispatch/registry gaps).

[15 — Graph Explorer](15-graph-explorer.md) shares its `reactflow` foundation with the
already-shipped workflow-builder UI (`resources/js/arazzo-ui.jsx`), but is a distinct feature
(execution observability vs. workflow construction) — not prior art to reuse wholesale, just
a tech-stack head start.

## Phases

### Phase 0 — Engine foundation (finish what's already scaffolded)
Almost everything else in this roadmap reads from or dispatches through these three. Do them
first, in this order — persistence before control flow, since the worker needs somewhere to
read/write state; data pipelining before control flow, since success-criteria/retry parsing
needs real output extraction to evaluate against.

1. [Zero-Code Data Pipelining](01-zero-code-data-pipelining.md)
2. [CQRS & Event-Sourced Persistence](02-cqrs-event-sourced-persistence.md)
3. [Native Asynchronous Control Flow](03-native-async-control-flow.md)

### Phase 1 — Core reliability primitives (depend on Phase 0)
4. [Strict Runtime Schema Validation](04-strict-runtime-schema-validation.md)
5. [Idempotency & Replay Safeguards](05-idempotency-replay-safeguards.md)
6. [SLA Monitors & Dead Letter Workflows](06-sla-monitors-dead-letter-workflows.md)

### Phase 2 — Advanced orchestration (depend on Phase 0 + 1)
7. [Automated Saga Pattern (Compensation Engine)](07-automated-saga-compensation-engine.md)
8. [Dynamic Fan-Out / Fan-In](08-dynamic-fan-out-fan-in.md)

### Phase 3 — Modular systems & AI integration (depend on Phase 0)
9. [Cross-Module Bounded Context Bridges](09-cross-module-bounded-context-bridges.md)
10. [AI Agent & Epistemic Protocol Routing](10-ai-agent-epistemic-protocol-routing.md)
11. [Multi-Tenancy Isolation](11-multi-tenancy-isolation.md)

### Phase 4 — Testing & developer tooling (can start once Phase 0 exists, parallelizable)
12. [Local Mocking Engine (Pest v3 Integration)](12-local-mocking-engine-pest.md)
13. [Interactive REPL Debugging Hooks](13-interactive-repl-debugging-hooks.md)
14. [Pre-Flight Linter](14-pre-flight-linter.md)

### Phase 5 — UI: core execution & observability (needs Phase 0's event ledger to have data)
15. [The Graph Explorer](15-graph-explorer.md)
16. [The Event Ledger](16-event-ledger.md)
17. [The Payload Inspector](17-payload-inspector.md)
18. [Retry & Intervention Controls](18-retry-intervention-controls.md)

### Phase 6 — UI: advanced debugging (needs Phase 5's views to hang off of)
19. [Interactive Time-Travel Debugger](19-interactive-time-travel-debugger.md)
20. [JSONPath Visual Diffing](20-jsonpath-visual-diffing.md)
21. [Webhook Payload Interception UI](21-webhook-payload-interception-ui.md)

### Phase 7 — UI: system health & scaling ops (needs real traffic/volume across workflows)
22. [Blast Radius Analyzer (Heatmap)](22-blast-radius-analyzer.md)
23. [Error Triage Board](23-error-triage-board.md)
24. [Golden Path Overlay](24-golden-path-overlay.md)

### Phase 8 — UI: DX & ecosystem integration (depend on most everything above existing)
25. [Execution Waterfall (Performance Profiler)](25-execution-waterfall-profiler.md)
26. [Visual Version Diffing](26-visual-version-diffing.md)
27. [Visual Saga Tracing](27-visual-saga-tracing.md) — needs [07](07-automated-saga-compensation-engine.md)
28. [The Ecosystem Bridge](28-ecosystem-bridge-horizon-telescope.md)
29. [Dry-Run Sandbox](29-dry-run-sandbox.md) — needs [12](12-local-mocking-engine-pest.md)

## How to use this

For each stub, when it's time to design it: run `superpowers:brainstorming` on that file. The
output becomes `docs/superpowers/specs/<date>-<slug>-design.md` and
`docs/superpowers/plans/<date>-<slug>.md`, same convention as every shipped feature. Once
implemented and reviewed, its summary moves into `CHANGELOG.md` and this roadmap stub can be
deleted (or marked done) — same cleanup pattern already used for queue-integration.
