# Prompt: write epistemic matrix (`epistemic-matrix`)

Section: `## 📊 Epistemic Verification Matrix`
Owns the gate codes: `tier-invalid`.

This prompt separates verified ground-truths from inferred assumptions: one
row per claim, with a tier the body can evidence and the evidence itself.
Tiers: `T1` executed, `T2` read in source, `T3` inferred, `T4` unverified.

## Inputs this prompt may read

- Claims made in `source-grounding` and the acceptance criteria — cite them,
  don't repeat them.
- Tests executed in this session (T1), source lines read (T2), inferences
  drawn from them (T3).
- Anything still unverified: mark it `T4`.

## Output template

Use exactly this heading — do not rename it:

```markdown
## 📊 Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| <claim> | T1 | <how it was evidenced> | <how to verify> |
```

Rules:

- Every data row must name a tier drawn from T1–T4 in its second cell — any
  other value, or a missing cell, fails `tier-invalid`. Header and separator
  rows are never data rows.
- Every data row must name evidence — a claim without an evidence line is an
  unverified claim and must be tier `T4`.
- `T4` marks an unverified claim: it passes the gate but blocks
  `ready-for-agent` until the claim is re-verified (readiness
  `tier-unverified`).
- Do not restate other sections — cite `R#` ids and source-grounding lines
  instead.
- Inherits prompt-preamble.md.

## Worked example

A real, conforming section — the engine accepts this exact content for
`epistemic-matrix` with zero findings.

```markdown
| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| The resolver throws on string bodies | T1 | phpunit WorkflowEngineTest::it_throws_on_string_body | run the named test |
| The error names the offending expression | T2 | src/Resolver.php#L10-L12 (commit-pinned permalink) | read the pinned lines |
| All callers catch the exception | T3 | call sites in src/Runner.php, inferred from signatures | trace each caller |
```