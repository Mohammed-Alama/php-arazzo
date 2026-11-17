# ADR-002: Conformance capability manifest with drift guard

## Status

Accepted

## Date

2026-11-17

## Issue

#340 — `chore(core): emit a machine-readable conformance capability manifest with a drift guard`

## Context

`docs/CONFORMANCE.md` is a generated Markdown table recording what the vendored OAI Arazzo corpus does at a point in time, but it is prose, not a contract:

- there is no machine-readable list of supported capabilities;
- there is no per-capability status (`supported`, `partial`, `unsupported`, `not-applicable`) with a reason;
- there is no CI check that fails when a capability regresses;
- the claim in `SelectorTypeSupportedRule` that `rfc9535` is supported is not connected to any test evidence (see #338).

## Decision

Emit a machine-readable conformance capability manifest (`docs/conformance/manifest.json`) with a drift guard, so conformance claims are testable and silent regressions are caught in CI.

### Manifest structure

JSON with `schemaVersion`, `arazzoVersions`, `capabilities[]`, `corpus[]`. Each capability has:

- `id` (string) — capability identifier (e.g. `parse`, `validate.structural`, `execute.sync`)
- `title` (string) — human-readable name
- `status` — one of `supported`, `partial`, `unsupported`, `not-applicable` (closed enum; free text rejected)
- `evidence` (array) — test IDs or corpus document paths substantiating the status; `supported` requires ≥1 evidence entry
- `notes` (string)

Minimum capabilities: parse, validate (structural), validate (semantic), execute (sync), execute (async/queue), selectors, expressions, criteria, dry-run, replay, trace, parallel, test-runner.

### Generator

`scripts/generate-conformance-matrix.php` — same CLI invocation, same corpus run. Additional output: `docs/conformance/manifest.json` written alongside `docs/CONFORMANCE.md` from the **same in-memory manifest model**. No new CLI flags; deterministic output (byte-identical across runs on the same corpus).

### Checker

New `scripts/check-conformance-manifest.php` — rebuilds the manifest model from the corpus, compares to the committed `docs/conformance/manifest.json`; exit 0 if in sync, non-zero with unified diff on drift. CI consumes the exit code.

### CI

`.github/workflows/ci.yml` runs `php scripts/check-conformance-manifest.php` on every PR; non-zero exit fails the job (R6).

### Location

`docs/conformance/manifest.json` — new top-level `docs/conformance/` subdirectory, alongside `docs/CONFORMANCE.md`. Deliberately NOT inside `docs/generated/`: `generate-docs.php` scans the source tree for architecture diagrams; the conformance manifest is corpus-driven (depends on vendored OAI examples and engine behavior), not source-tree-driven. Different trigger, different freshness semantics.

### Docs

`docs/CONFORMANCE.md` links to the manifest and explains the four-value status vocabulary and what `evidence` means.

## Deepening opportunity (implemented)

Extract `ConformanceManifestBuilder::build(array $documents): array` — a deep module in `packages/core/tests/Conformance/` hiding corpus orchestration, capability→status mapping, and deterministic JSON serialization. Both generator and checker depend on this one seam; tests target one class instead of two script invocations.

## Alternatives Considered

### Manifest inside docs/generated/

Rejected: `docs/generated/` is source-tree-scanned architecture diagrams with different freshness semantics. The conformance manifest is corpus-driven.

### Free-text status values

Rejected: free text is not machine-consumable. Closed four-value enum keeps tooling simple.

## Consequences

- Manifest becomes the contract other implementations can compare against.
- `supported` claims require evidence — addresses #338's `rfc9535` support concern.
- CI fails on drift; the manifest must be regenerated before a PR can merge.
- Rollback: delete the manifest, the checker script, and the CI step; the Markdown matrix remains.

## Location note

Created under `docs/adr/` per the repository's ADR directory convention.
