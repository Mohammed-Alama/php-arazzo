# Prompt: write problem statement (`problem-statement`)

Section: `## 📝 Problem Statement & Intent`
Owns the gate codes: `missing-requirement`.

This prompt turns the observed symptom (bugfix) or the intent (feature) into
numbered requirements `R1..Rn`. It never restates the preamble or any other
section — it establishes the requirements that every later section cites by
`R#` id (acceptance scenarios, source-grounding claims). The body is a linked
structure, not a stack of echoes.

## Inputs this prompt may read

- The reporter's description: observed vs expected behavior (bugfix) or
  intent (feature), under 150 words.
- Any linked parent issue's problem statement — cite it by number, never
  copy it into this section.
- Reproduction steps or exact commands that exhibit the symptom.

## Output template

Use exactly this heading — do not rename it:

```markdown
## 📝 Problem Statement & Intent

Observed: <what actually happens, one line>
Expected: <what should happen, one line>

R1. <one observable, measurable sentence>
[R2. <one observable, measurable sentence>]
```

Rules:

- At least one `R<n>. ` line is mandatory — a statement without a numbered
  requirement fails `missing-requirement`.
- Each requirement is one sentence, observable in behavior or test output,
  and names no design decision (decisions belong in `feature-scope` as `D#`).
- No solutions, no interfaces, no packages here — those sections follow.
- Inherits prompt-preamble.md.

## Worked example

A real, conforming section — the engine accepts this exact content for
`problem-statement` with zero findings.

```markdown
Observed: the resolver returns null for a JsonPath expression over a string body instead of throwing.
Expected: it throws UnresolvedWorkflowException so callers never handle a silent null.

R1. The resolver throws UnresolvedWorkflowException when the body is a string.
R2. The resolver's error names the offending expression.
```