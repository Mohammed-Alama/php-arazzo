# 08: Vendor cross-ecosystem control flow, retry, and cross-document test fixtures

**What to build:**
Import upstream golden fixtures from `arazzo-toolkit` covering control flow transitions, retry repair references, and cross-document workflows into `packages/core/tests/fixtures/`, verifying that `php-arazzo` produces identical results across the shared conformance suite and updating `docs/CONFORMANCE.md`.

**Blocked by:**
- 03: Support step-level goto to workflowId as one-way transfer
- 04: Implement retry action repair reference execution with budget accounting
- 05: Support cross-document workflow references ($sourceDescriptions.{name}.{workflowId})

**Status:** ready-for-agent

- [ ] Vendor control-flow fixture suite (`workflow-control-flow.arazzo.yaml`, `cross-document.arazzo.yaml`, `cross-document-child.arazzo.yaml`) from `arazzo-toolkit/packages/runner/test/fixtures/` into `packages/core/tests/fixtures/`.
- [ ] Add fixture documentation entries in `packages/core/tests/fixtures/README.md`.
- [ ] Implement end-to-end conformance test scenarios in `packages/core/tests/Feature/ConformanceTest.php`.
- [ ] Run `composer run conformance` to regenerate `docs/CONFORMANCE.md`.
- [ ] Ensure all vendored tests pass cleanly in Pest.
