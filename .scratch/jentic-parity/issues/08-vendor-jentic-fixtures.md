# 08: Vendor Jentic Petstore and e-commerce reference workflow fixtures

## Parent
Epic: Multi-Language Cross-Conformance Test Suite (PHP vs Jentic)

## What to build
Vendor reference Arazzo workflow documents and OpenAPI source descriptions from `jentic/jentic-arazzo-tools` and `jentic/arazzo-engine` into `packages/core/tests/fixtures/jentic/`. Add tests to verify that `php-arazzo` parses, validates, and evaluates them with 100% compatibility.

## Acceptance criteria
- [ ] Vendor Jentic Petstore and e-commerce workflows into `packages/core/tests/fixtures/valid/jentic/`.
- [ ] Add fixture documentation entries in `packages/core/tests/fixtures/README.md`.
- [ ] Implement parser and validation tests in `packages/core/tests/Feature/ConformanceTest.php` ensuring all Jentic fixtures pass.
- [ ] Verify expression evaluation matches expected outputs for Jentic workflow scenarios.

## Blocked by
None (can start immediately).
