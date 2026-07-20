# 09. Cross-Module Bounded Context Bridges

**Category:** Backend — Modular Systems & AI Integration
**Phase:** 3 — Modular systems & AI integration
**Depends on:** [03 — Native Asynchronous Control Flow](03-native-async-control-flow.md)
**Status:** Not started — needs brainstorming

## Description

Extensible Action Bindings that allow the engine to map an Arazzo `stepId` directly to a
local, single-responsibility PHP Action class via an internal message bus, enabling
orchestration across strict modular boundaries without direct coupling.

**1.1.0 delta:** model this as an `x-laravel-action` vendor extension (string FQCN) on a
`Step`, resolved via Laravel's container instead of HTTP/AsyncAPI dispatch — a third
`StepProtocolExecutorInterface` implementation (`ActionBusStepExecutor`) alongside the
HTTP/AsyncAPI ones from [03](03-native-async-control-flow.md)'s delta. `operationId`/
`operationPath` become optional when this is set. Parse all `x-` extensions into one
`array $extensions` bag on the DTOs rather than a hardcoded field per extension, so future
vendor extensions (`x-ai-fallback-model`, etc. — see [10](10-ai-agent-epistemic-protocol-routing.md))
don't each need a DTO constructor change. Demonstrated in
`tests/fixtures/parser/arazzo-1.1-cross-protocol-saga.yaml`.
