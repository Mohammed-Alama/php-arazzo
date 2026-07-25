# Laravel Arazzo — Feature Roadmap

Split into **backend** (framework-agnostic engine features + AI + orchestration primitives)
and **frontend** (observability / debugging surfaces, each delivered through several bridges).

- Backend stubs live under `backend/phase-N-<name>/` — each file is prefixed with a
  category tag (`core`, `exec`, `ai`, `rel`, `tenant`, `dx`) so the domain reads at a glance.
- Frontend features live once under `frontend/features/` and are delivered by one or more
  bridges under `frontend/bridges/` (own-ui, filament, easyadmin, drupal-admin, standalone).

Shipped items no longer appear here; they moved into `CHANGELOG.md` via
`scripts/ship-plan.sh <slug>`. Their plans + specs live under
`docs/superpowers/plans/shipped/` and `docs/superpowers/specs/shipped/`.

## Ship convention

Every roadmap stub follows the same lifecycle:

1. **Brainstorm** the stub via `superpowers:brainstorming` → produces a design spec in
   `docs/superpowers/specs/<date>-<slug>-design.md`.
2. **Plan** the spec via `superpowers:writing-plans` → produces a plan in
   `docs/superpowers/plans/<date>-<slug>.md`.
3. **Execute** the plan (usually via `superpowers:subagent-driven-development`).
4. **Ship** via `scripts/ship-plan.sh <slug>` — deterministically moves plan + spec to
   `shipped/`, deletes the roadmap stub, and appends a bullet under `## Unreleased` →
   `### Shipped` in `CHANGELOG.md`.

The script is idempotent, no interactive prompts, safe to re-run.

## Prioritization

**Phase 0 runs on two parallel tracks:** foundation (already largely shipped) and AI
(prioritized from day one — Arazzo YAML is designed to be LLM-legible; the AI features are
the flagship product story, not a "later" enhancement).

## Backend

### `backend/phase-0-foundation/` — engine core

Initial foundation shipped (parser, validator, executor, expression resolver, event ledger,
async control flow, schema validation, idempotency — see CHANGELOG). New foundation-layer
stubs land here going forward.

| Stub | Tier | Purpose |
|---|---|---|
| [core-34-arazzo-1.1.0-spec](backend/phase-0-foundation/core-34-arazzo-1.1.0-spec.md) | OSS | Full Arazzo 1.1.0 support: AsyncAPI, Selector Object, sub-workflow composition, `in: querystring`, `$self` |

See [phase-0-foundation/README.md](backend/phase-0-foundation/README.md) for filing convention.

### `backend/phase-0-ai/` — AI, prioritized from day one

| Stub | Tier | Purpose |
|---|---|---|
| [ai-10-agent-routing](backend/phase-0-ai/ai-10-agent-routing.md) | pro | Epistemic protocol routing for multi-agent orchestrations |
| [ai-30-openapi-deterministic-gen](backend/phase-0-ai/ai-30-openapi-deterministic-gen.md) | OSS | Deterministic OpenAPI → Arazzo scaffold |
| [ai-31-openapi-refined-gen](backend/phase-0-ai/ai-31-openapi-refined-gen.md) | pro | LLM-refined generator (OpenAI / Anthropic / Ollama) |
| [ai-32-workflow-designer-agent](backend/phase-0-ai/ai-32-workflow-designer-agent.md) | pro | Interactive multi-turn designer with graph-aware mutations |

### `backend/phase-1-reliability/`

| Stub | Tier | Purpose |
|---|---|---|
| [rel-06-sla-monitors-dlq](backend/phase-1-reliability/rel-06-sla-monitors-dlq.md) | pro | SLA monitors + dead-letter workflows |

### `backend/phase-2-orchestration/`

| Stub | Tier | Purpose |
|---|---|---|
| [exec-07-saga-compensation](backend/phase-2-orchestration/exec-07-saga-compensation.md) | pro | Automated saga compensation engine |
| [exec-08-fan-out-in](backend/phase-2-orchestration/exec-08-fan-out-in.md) | pro | Dynamic fan-out / fan-in |

### `backend/phase-3-modularity/`

| Stub | Tier | Purpose |
|---|---|---|
| [tenant-09-context-bridges](backend/phase-3-modularity/tenant-09-context-bridges.md) | pro | Cross-module bounded-context bridges |
| [tenant-11-multitenancy](backend/phase-3-modularity/tenant-11-multitenancy.md) | pro | Multi-tenancy isolation |
| [tenant-33-oak-catalog](backend/phase-3-modularity/tenant-33-oak-catalog.md) | OSS + pro | Jentic OAK catalog bridge (6000-API library) |

### `backend/phase-4-dx/`

| Stub | Tier | Purpose |
|---|---|---|
| [dx-12-pest-mocking](backend/phase-4-dx/dx-12-pest-mocking.md) | OSS | Local mocking engine (Pest v3+) |
| [dx-13-repl-hooks](backend/phase-4-dx/dx-13-repl-hooks.md) | pro | Interactive REPL debugging hooks |
| [dx-14-linter](backend/phase-4-dx/dx-14-linter.md) | OSS | Pre-flight linter (`arazzo lint`, `--against-openapi` drift check) |

## Frontend

Each feature is specified once under `frontend/features/`. Bridges implement or embed the
same feature across delivery surfaces (own-ui, Filament, EasyAdmin, Drupal admin, standalone).

### `frontend/features/` — per-feature specs

| Stub | Category | Purpose |
|---|---|---|
| [obs-15-graph-explorer](frontend/features/obs-15-graph-explorer.md) | observability | Live execution graph |
| [obs-16-event-ledger](frontend/features/obs-16-event-ledger.md) | observability | Immutable event stream per run |
| [obs-17-payload-inspector](frontend/features/obs-17-payload-inspector.md) | observability | Per-step input/output snapshots |
| [obs-18-retry-controls](frontend/features/obs-18-retry-controls.md) | observability | Retry / intervention actions |
| [debug-19-time-travel](frontend/features/debug-19-time-travel.md) | debugging | Interactive time-travel debugger |
| [debug-20-jsonpath-diff](frontend/features/debug-20-jsonpath-diff.md) | debugging | Visual JSONPath diffing between runs |
| [debug-21-webhook-interception](frontend/features/debug-21-webhook-interception.md) | debugging | Live webhook payload interception UI |
| [health-22-blast-radius](frontend/features/health-22-blast-radius.md) | system-health | Blast-radius heatmap for failing workflows |
| [health-23-error-triage](frontend/features/health-23-error-triage.md) | system-health | Error triage board |
| [health-24-golden-path](frontend/features/health-24-golden-path.md) | system-health | Golden-path overlay |
| [perf-25-waterfall](frontend/features/perf-25-waterfall.md) | performance | Execution waterfall profiler |
| [diff-26-version-diff](frontend/features/diff-26-version-diff.md) | dx | Visual version diffing |
| [saga-27-saga-tracing](frontend/features/saga-27-saga-tracing.md) | debugging | Visual saga tracing — needs exec-07 |
| [bridge-28-horizon-telescope](frontend/features/bridge-28-horizon-telescope.md) | ecosystem | Horizon / Telescope cross-linking |
| [test-29-dry-run-sandbox](frontend/features/test-29-dry-run-sandbox.md) | dx | Dry-run sandbox — needs dx-12 |

### `frontend/bridges/` — per-framework delivery

| Bridge | Package | Phase |
|---|---|---|
| [own-ui](frontend/bridges/own-ui/README.md) | `alama/arazzo-pro-ui` + OSS shell `alama/arazzo-ui-oss` | C |
| [filament](frontend/bridges/filament/README.md) | `alama/arazzo-pro-filament` | C (primary agency surface) |
| [easyadmin](frontend/bridges/easyadmin/README.md) | `alama/arazzo-pro-symfony-easyadmin` | E |
| [drupal-admin](frontend/bridges/drupal-admin/README.md) | `alama/arazzo-pro-drupal-admin` | E |
| [standalone](frontend/bridges/standalone/README.md) | `alama/arazzo-ui-standalone` (OSS) + pro overlay | C |

Phase letters match the commercial plan in
[docs/superpowers/specs/2026-07-24-commercial-tier-and-multi-framework-design.md](../specs/2026-07-24-commercial-tier-and-multi-framework-design.md).

## Arazzo 1.1.0 delta

The 1.1.0 spec support work is now its own stub — [core-34-arazzo-1.1.0-spec](backend/phase-0-foundation/core-34-arazzo-1.1.0-spec.md).
Land it before (or alongside) the downstream stubs that use 1.1.0 constructs so they are
1.1.0-native from day one:

- [ai-10-agent-routing](backend/phase-0-ai/ai-10-agent-routing.md) — Selector + AsyncAPI routing
- [exec-07-saga-compensation](backend/phase-2-orchestration/exec-07-saga-compensation.md) — sub-workflow composition on Failure Actions
- [tenant-09-context-bridges](backend/phase-3-modularity/tenant-09-context-bridges.md) — AsyncAPI cross-context messaging

## How to use this document

- **Picking what to work on next?** Start at Phase 0-AI, then walk down phases. Within a
  phase, prefer OSS before pro (broader test surface first).
- **Adding a new roadmap idea?** Drop a stub in the appropriate `backend/phase-*/` or
  `frontend/features/` folder using the naming convention `<category>-<NN>-<slug>.md`, then
  link it from the tables above.
- **Shipping a plan?** `scripts/ship-plan.sh <slug>` — everything else (CHANGELOG update,
  stub removal, plan/spec move to `shipped/`) is handled deterministically.
