---
name: add-fixture
description: Add a conformance fixture document (valid, invalid, or edge-case Arazzo file) to packages/core/tests/fixtures with README entry. Use when adding test fixtures for parser or validator coverage.
---

# Add Conformance Fixture

Fixtures are data-driven test inputs: files under `packages/core/tests/fixtures/{valid,invalid,edge-cases}/` are picked up automatically by the conformance datasets — placement is the contract.

## Step 1 — Generate

```bash
php .agents/skills/add-fixture/scripts/new-fixture.php <kind> <slug-name> [--json] [--desc "..."] [--reason "..."]
```

- `kind`: `valid` (must parse+validate clean), `invalid` (must fail — `--reason` required), or `edge-cases` (inert until wired to a test).
- The script writes a minimal-but-valid skeleton, adds the README bullet under the right section, and refuses to overwrite.
- Invalid skeletons ship intentionally broken (unresolvable `$ref`, mirroring `invalid/unresolvable-ref.arazzo.yaml`) so the suite stays honest — replace the breakage with the specific defect you want to pin.

## Step 2 — Shape the fixture

Edit the skeleton toward the smallest document that exercises the target behaviour. One fixture pins one behaviour; split behaviours across fixtures.

## Step 3 — Verify

```bash
cd packages/core && vendor/bin/pest tests/Feature/ConformanceTest.php
```

Both datasets must pass: `valid` fixtures validate cleanly; `invalid` fixtures must fail (parse rejection or at least one validation error).

## Edge cases are different

`edge-cases/` is not in any dataset. After generating there, write a dedicated test next to `tests/Feature/EdgeCaseFixturesTest.php` asserting the exact expected outcome (including the specific error `code`), otherwise the file is dead weight — delete it instead.
