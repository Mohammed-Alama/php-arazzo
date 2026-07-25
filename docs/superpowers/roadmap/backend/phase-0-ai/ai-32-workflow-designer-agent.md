# Workflow Designer Agent (Interactive Refinement)

Category: **ai** · Phase: **0-ai** · Tier: **Pro** (`arazzo-pro-ai`)
Depends on: [ai-31 refined generator](./ai-31-openapi-refined-gen.md), obs-16 event ledger, obs-17 payload inspector

## Problem

One-shot AI refinement (ai-31) closes 80% of cases. The other 20% need iteration: "add
retry with backoff on the payment step", "if the shipping API returns 5xx, fall back to
manual queue", "expose the order total as an output". Doing that as a text-based chat over
a YAML file loses spatial context; doing it inside a JSON diff loses the ability to preview.
The designer agent binds a chat pane to the React Flow canvas (already shipped) so the LLM
mutates the visual graph directly, and vice versa.

## Feature

Backend side of the loop (frontend delivery under `frontend/features/` – graph explorer/designer):

- `DesignerSessionInterface` — server-side session that owns the current workflow AST,
  applies tool-called mutations (`addStep`, `removeStep`, `wireOutput`, `setSuccessCriteria`,
  `attachCompensation`), and re-validates after each mutation.
- Tool schema exposed to the LLM as strict JSON schema, so mutations round-trip through the
  validator before landing.
- Streaming diff push over Server-Sent Events (SSE) — the UI shows the LLM's proposed edits
  live in the canvas with an accept/reject bar.
- Uses ai-31's refiner as the underlying model call; adds turn state, prior-message caching,
  and mutation history.

## Why prioritize (Phase 0-ai)

Same argument as ai-31: shipping the AI story in phase 0 anchors the product identity. The
designer agent is the "moment of magic" demo — user types "make this idempotent" and watches
the graph re-wire.

## Acceptance

- Multi-turn session persists across chat messages; validator runs after each tool call.
- No invalid workflow is ever committed to the session state (rollback on validator failure).
- Latency: p50 first-token < 800ms with prompt caching; p50 full mutation applied < 3s.
- Playwright end-to-end: "add retry" and "wire output X to step Y" both succeed against a
  mock LLM backend.

## Out of scope

- Non-workflow assistance (docs Q&A, general chat) — separate skill, not here.
- Voice input — v2.
