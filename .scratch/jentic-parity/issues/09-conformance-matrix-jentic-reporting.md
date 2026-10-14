# 09: Add cross-engine execution verdict assertions to conformance matrix

## Parent
Epic: Multi-Language Cross-Conformance Test Suite (PHP vs Jentic)

## What to build
Extend the conformance matrix generator (`scripts/generate-conformance-matrix.php`) and `docs/CONFORMANCE.md` to track Jentic cross-engine conformance. Document step status verdicts, output mapping comparisons, and performance benchmarks comparing PHP execution with Python and TypeScript results.

## Acceptance criteria
- [ ] Update `scripts/generate-conformance-matrix.php` to include Jentic test fixtures in the matrix run.
- [ ] Add a dedicated "Cross-Engine Conformance: Jentic vs PHP" section to `docs/CONFORMANCE.md`.
- [ ] Assert that terminal execution status (`succeeded`) and outputs match across both implementations.
- [ ] Ensure `composer run conformance` generates the matrix without regressions.

## Blocked by
- #98
