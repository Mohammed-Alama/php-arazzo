# Prompt: write dependency matrix (`dependency-matrix`)

Section: `## 📦 System Dependency Matrix`
Owns the gate codes: none.

This prompt lists what this change touches, upstream and downstream. It owns
no gate code, so it is never a reason for the gate to reject an issue — but it
must still be honest: list only packages this change actually imports,
extends or configures. Never list packages "just in case".

## Inputs this prompt may read

- `composer.json` / `composer.lock` of the affected package(s).
- `R#` ids from `problem-statement` cited by role, never repeated.
- The `D#` decisions of `feature-scope` that constrain which packages may be
  touched.

## Output template

Use exactly this heading — do not rename it:

```markdown
## 📦 System Dependency Matrix

| Package | Role | Version | Touched by this change |
| --- | --- | --- | --- |
| <package> | <what it does here> | <constraint from composer.lock> | yes/no |
```

Rules:

- Every row names a package this change imports, extends, or configures —
  rows exist because the change touches them, not "just in case".
- Version constraints come from `composer.lock`, never from memory.
- A row marked `no` is allowed: it documents a dependency this change sits
  next to but does not touch.
- No packages beyond those rows; this section is a matrix, not a narrative.
- Inherits prompt-preamble.md.

## Worked example

A real, conforming section — the engine accepts this exact content for
`dependency-matrix` (this section owns no checks).

```markdown
| Package | Role | Version | Touched by this change |
| --- | --- | --- | --- |
| alama/arazzo-core | the resolver and the engine | ^1.4 | yes — WorkflowEngine::resolve() |
| symfony/yaml | schema parsing | ^6.4 | no |
```