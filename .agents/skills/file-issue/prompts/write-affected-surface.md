# Prompt: write affected surface (`affected-surface`)

Section: `## 🛠️ Affected Surface & Rollback`
Owns the gate codes: `rollback-missing`.

This prompt names the packages touched, the risk level, and how to roll back.
It never lists packages the dependency matrix does not — cite `dependency-matrix`
rows instead of repeating them.

## Inputs this prompt may read

- The `dependency-matrix` rows marked "touched" (cite packages, don't copy).
- Test files and entry points this change affects.
- The deployment context: images, feature flags, config keys.

## Output template

Use exactly this heading — do not rename it:

```markdown
## 🛠️ Affected Surface & Rollback

Packages: <comma-separated package names from the dependency matrix>
Risk: low | medium | high — <blast radius in one line>
Rollback: <one step with at least one word>
```

Rules:

- A `Rollback: <step>` line with at least one word after it is mandatory —
  without it the section fails `rollback-missing`.
- `Risk:` names the blast radius: which requests, users or data a failure in
  this change can reach.
- Every package named here must appear in `dependency-matrix` — cite it,
  never restate it.
- No `D#` lines here — open decisions live in `feature-scope`.
- Inherits prompt-preamble.md.

## Worked example

A real, conforming section — the engine accepts this exact content for
`affected-surface` with zero findings.

```markdown
Packages: alama/arazzo-core (WorkflowEngine)
Risk: low — one thrown exception added, no new entry points
Rollback: revert the commit and redeploy the previous image
```