# Jentic Ecosystem Comparison & Collaboration Notes

**Status:** Research note
**Created:** 2026-07-20
**Related:** `docs/superpowers/roadmap/ROADMAP.md`, `docs/superpowers/roadmap/10-ai-agent-epistemic-protocol-routing.md`

Jentic (`github.com/jentic`) maintains the closest thing to a reference implementation of Arazzo outside the OpenAPI Initiative spec itself. This note compares their stack against this repo's roadmap and lays out concrete ways to collaborate rather than build in isolation.

## What Jentic actually ships

Two separate implementations — not one codebase, not one language:

| Repo | Language | Components | License | Activity |
|---|---|---|---|---|
| [jentic/arazzo-engine](https://github.com/jentic/arazzo-engine) | Python | Runner (CLI + library, executes workflows) + Generator (OpenAPI → Arazzo) | Apache-2.0 | 59★, 147 commits, active, latest release Oct 2025 |
| [jentic/jentic-arazzo-tools](https://github.com/jentic/jentic-arazzo-tools) | TypeScript | `@jentic/arazzo-parser`, `-resolver`, `-validator`, `-runner`, `-ui` | Apache-2.0 | 20★, 31 releases, actively maintained |

Plus a broader platform: [`jentic-mini`](https://github.com/jentic/jentic-mini) (self-hosted agent↔API broker — credential injection at request time, not a workflow engine), and OAK, a catalog of 6,000+ APIs / 2,000+ workflows for agent discovery. Per [docs.jentic.com](https://docs.jentic.com/), the platform leans on OpenAPI + Arazzo + MCP together, with a "Standard Agent" framework and "Just-in-Time Tooling" (on-demand tool loading for agents) as their answer to agent orchestration.

**Key finding:** `jentic-arazzo-tools`' own docs state support for **Arazzo 1.0.0 / 1.0.1 only** — no AsyncAPI `sourceDescriptions`, no Selector Object, no `workflowId`+`parameters` composition. Confirmed live via GitHub/docs fetch on 2026-07-20, not from training data. This repo's 1.1.0 upgrade work (see `docs/superpowers/roadmap/ROADMAP.md`'s "Arazzo 1.1.0" section) is therefore ahead of the reference implementers on spec currency, not catching up to them.

## Roadmap overlap, phase by phase

- **Phase 0-2** (01 pipelining, 02 CQRS persistence, 03 async control flow, 04-08 reliability/saga) — **no overlap**. Neither Jentic repo does event-sourced persistence, CQRS, idempotency/replay, SLA monitors, or saga compensation. This is this engine's actual differentiator, not redundant effort.
- **04 schema validation / 14 pre-flight linter** — closest overlap. `@jentic/arazzo-validator` does JSON-Schema + semantic linting, the same job these two roadmap items do. Worth diffing their rule set against this repo's 39 validation rules for free coverage gaps.
- **09 cross-module bounded-context bridges / 10 AI agent & epistemic protocol routing** — Jentic's strongest ground. Their MCP integration (ships working connections to Claude Desktop, Cursor, Windsurf, ChatGPT, VS Code) plus the Standard Agent framework and JITT is a shipped answer to what item 10 is designing from scratch. Read their docs before finalizing that item's design — either align with their conventions or explicitly document why this engine diverges.
- **15-17 UI** (graph explorer, event ledger, payload inspector) — `@jentic/arazzo-ui` is comparable but read-only visualization ("Swagger UI for workflows"). This repo's shipped React Flow builder plus planned execution-observability items go further (interactive construction + live execution state, not just static viewing).

## Collaboration options, ranked by effort

1. **File a 1.1.0-gap issue on `jentic-arazzo-tools`**, referencing this repo's `tests/fixtures/parser/arazzo-1.1-cross-protocol-saga.yaml` fixture and the Selector-Object polymorphic-`type`-field parsing edge case identified during the 1.1.0 architecture consult. Concrete, low-effort, and the kind of report that gets a maintainer's attention because it comes with a working fixture, not just a complaint.
2. **Join their Discord** (linked from the `arazzo-engine` README) — a direct line to people closest to the actual OAI Arazzo spec editors. Useful for resolving 1.1.0 interpretation questions (e.g. exact `Expression Type Object` version-pinning semantics) while implementing, instead of guessing and finding out later.
3. **Propose a cross-language conformance fixture set** — identical `.yaml` fixtures plus a shared expected-behavior assertion format, run against the Python runner, the TypeScript runner, and this PHP engine. Nobody has built this yet as far as the public docs show; it would catch spec-interpretation drift across all three implementations, not just bugs in one.
4. **Don't design item 10 (AI/MCP routing) in a vacuum** — read Jentic's MCP/Standard-Agent/JITT docs first. Adopt compatible conventions where it costs nothing, and write down explicitly where and why this engine's design diverges, so the divergence is a decision on record rather than an accident of not having looked.

## Sources

- [jentic/arazzo-engine](https://github.com/jentic/arazzo-engine)
- [jentic/jentic-arazzo-tools](https://github.com/jentic/jentic-arazzo-tools)
- [Jentic documentation](https://docs.jentic.com/)
- [Arazzo Engine overview](https://docs.jentic.com/reference/arazzo-engine/overview/)
