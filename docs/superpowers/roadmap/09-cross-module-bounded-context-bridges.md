# 09. Cross-Module Bounded Context Bridges

**Category:** Backend — Modular Systems & AI Integration
**Phase:** 3 — Modular systems & AI integration
**Depends on:** [03 — Native Asynchronous Control Flow](03-native-async-control-flow.md)
**Status:** Not started — needs brainstorming

## Description

Extensible Action Bindings that allow the engine to map an Arazzo `stepId` directly to a
local, single-responsibility PHP Action class via an internal message bus, enabling
orchestration across strict modular boundaries without direct coupling.
