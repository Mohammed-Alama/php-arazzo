# Phase 0 — Foundation

Framework-agnostic engine core: parser, validator, expression resolver, executor,
persistence, async control flow.

**All initial foundation work has shipped** — see `## Unreleased` → `### Shipped` in
`CHANGELOG.md`, and `docs/superpowers/plans/shipped/` for the executed plans.

## What lives here going forward

Only *new* foundation-level stubs. Add a stub when the work:

- Touches the pure-PHP engine core (`Alama\Arazzo\*`), not a framework bridge.
- Belongs below reliability / orchestration / AI / tenancy / DX in the layer stack.
- Is not a UI concern (those live under `frontend/`).

## Current stubs

| Stub | Category | Purpose |
|---|---|---|
| [core-34-arazzo-1.1.0-spec](core-34-arazzo-1.1.0-spec.md) | core | Full Arazzo 1.1.0 spec support |
| [core-35-cli-arazzo-bin](core-35-cli-arazzo-bin.md) | core | Standalone `bin/arazzo` CLI |
| [core-36-license-verifier](core-36-license-verifier.md) | core | `LicenseVerifierInterface` + null impl |
| [core-37-dependency-graph](core-37-dependency-graph.md) | core | Topological order + cycle detection |
| [core-38-event-dispatcher-wiring](core-38-event-dispatcher-wiring.md) | core | PSR-14 event catalog |
| [core-39-metrics-recorder-interface](core-39-metrics-recorder-interface.md) | core | `MetricsRecorderInterface` |
| [core-40-workflow-snapshot-serialization](core-40-workflow-snapshot-serialization.md) | core | Canonical byte-identical snapshots |

Naming: `<category>-<NN>-<slug>.md` — categories used at this layer are `core`, `exec`,
`persist` (subsume into `core` unless volume warrants a split).
