# ADR-001: Strict response validation by default

## Status

Accepted

## Date

2026-11-17

## Context

`arazzo run` currently builds its runner with a fake PSR-18 client and stubbed executor, output extractor and response validator (issue #334). Part of fixing that wiring is making the command report a true result: a response that violates its OpenAPI response schema must fail the step and produce a non-zero exit code.

The runner and its adapters gate that behaviour on a strict-validation policy whose default is `false`:

- `packages/runner/src/Sync/StepExecutor.php` — `strictValidationDefault = false`
- `packages/runner/src/AsyncGraphSeams.php` — corresponding seam default
- `packages/protocol-http/src/Protocol/HttpStepExecutor.php` and `packages/protocol-http/src/Registrar/ProtocolHttpRegistrar.php` — protocol-http mirrors
- `config/arazzo.php` and its explicit consumers `packages/laravel/src/Support/AsyncGraphResolver.php` and `packages/laravel/src/ProtocolHttpServiceProvider.php`
- user-facing defaults in `README.md`, `packages/core/README.md` and `packages/laravel/README.md`

With the default off, a conformance-style acceptance test passes only if it explicitly opts in per step. The Stage 3 review for #334 recorded this as a blocker for R4.

## Decision

Change the default validation policy to strict (`true`) across the runner contract and its protocol-http and Laravel mirrors. A step may still opt out explicitly with `x-strict-validation: false`.

This is a **semantic backward-compatibility break**: no constructor signature changes, but runs that previously accepted off-schema responses silently will now fail. A release note / changelog entry is required.

The CLI composition-root wiring for #334 is separate from this policy decision and is implemented in that issue. The `HttpExecutionAdapters` extraction proposed in the review is a non-blocking follow-up, not part of this decision.

## Alternatives Considered

### Opt in per test step

Leave the default `false` and set `x-strict-validation: true` on the acceptance test step.

- Pros: no behavioural change for existing users; smallest diff.
- Cons: the acceptance test no longer proves the default behaviour that users get from `arazzo run`; the insecure default remains the documented one.
- Rejected: the human owner chose to change the runner contract default.

### Leave validation off by default

- Pros: zero compatibility risk.
- Cons: contradicts the spec-facing goal that a schema-violating response fails a step; conformance results would be misleading.
- Rejected.

## Consequences

- Default behaviour becomes fail-closed for off-schema responses; previously passing runs may start failing.
- Existing documents that need lenient behaviour must add `x-strict-validation: false` to the relevant step.
- Required implementation touches: the runner sync and seam defaults, protocol-http mirrors, the Laravel config default and its explicit consumers, and three READMEs.
- Required verification: `StepExecutorTest` for the new default, `AsyncGraphSeamsTest`, protocol-http tests where mirrored, `RunCommandTest` covering failure rendering and exit code, sync and `QueueFixtureRunner` fixture parity, `make verify`, `make test-mutate`, and the falsification scripts.
- A release note must call out the semantic break.

## Location note

The repository had no established ADR directory or numbering convention at the time of writing, so this record uses the default `docs/decisions/ADR-NNN-title.md` convention.
