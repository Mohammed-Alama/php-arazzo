# Cross-Bridge (and cross-language) Conformance Harness

Category: **dx** · Phase: **4-dx** · Tier: **OSS**
Referenced by: commercial spec Section 8 · Enables Jentic coordination

## Problem

Four bridges (Laravel, Symfony, Drupal, standalone) will each ship an execution surface for
the same core. Without a shared conformance fixture set, bridges drift silently — a
workflow that runs green in Laravel will do subtly different things in Symfony. Same risk
across language implementations if Jentic collaboration lands (Python `arazzo-engine`,
TypeScript `jentic-arazzo-tools`).

## Feature

Add `tests/conformance/` inside `alama/arazzo-core`:

```
tests/conformance/
  README.md                            # runner protocol
  fixtures/
    01-linear-http.yaml                # workflow
    01-linear-http.expected.json       # golden: outputs, event ledger, step statuses
    02-parallel-fan-out.yaml
    02-parallel-fan-out.expected.json
    03-async-correlation-resume.yaml
    03-async-correlation-resume.expected.json
    04-saga-compensation.yaml          # once exec-07 lands
    05-selector-object-1.1.yaml        # once core-34 lands
    ...
  runner/
    ConformanceRunner.php              # boots engine, executes fixture, diffs against expected
    ExpectedResult.php                 # decoded golden JSON
    MockServer.php                     # deterministic HTTP + AsyncAPI responders
```

Every bridge repo (`laravel-arazzo`, `symfony-arazzo`, `drupal-arazzo`) imports
`alama/arazzo-core` as a dev dep and runs `ConformanceRunner` against all fixtures under
each bridge's DI container. Any behavior divergence = test failure.

Cross-language variant (if Jentic accepts): fixtures published to `jentic/oak-conformance`,
Python + TS + PHP runners each consume the same YAML + JSON pairs.

## Acceptance

- ≥ 20 fixtures covering: sync HTTP, async correlation, retry, saga (once shipped),
  Selector Object (once shipped), sub-workflow composition, error/goto/end actions.
- Runner produces byte-identical diff output regardless of language.
- CI job in every bridge repo runs the full suite on every PR.

## Out of scope

- Property-based / fuzz fixtures — see `dx-45`.
- Performance benchmarks — separate concern.
