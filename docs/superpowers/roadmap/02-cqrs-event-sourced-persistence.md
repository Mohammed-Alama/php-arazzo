# 02. CQRS & Event-Sourced Persistence

**Category:** Backend — Core Orchestration Engine
**Phase:** 0 — Engine foundation
**Depends on:** None (foundation)
**Status:** Not started — needs brainstorming

**Existing code:** `RedisHotStateStore`, `DatabaseEventLedger`, `InMemoryDefinitionRegistry`
already exist (see `CHANGELOG.md`, "Added — not yet wired into the runtime") but none are
bound in `LaravelArazzoServiceProvider`, there's no DB migration for the event ledger table,
and `InMemoryDefinitionRegistry` is process-local — not viable across real queue worker
processes. Brainstorm the wiring, the migration (including the date-range partitioning called
for below, which isn't built at all), and a real (non-in-memory) definition registry.

## Description

A highly scalable dual-store strategy. It utilizes Redis for the hot-state runtime cache to
prevent database lock contention, combined with an append-only PostgreSQL ledger utilizing
date-range partitioning for an immutable execution history.
