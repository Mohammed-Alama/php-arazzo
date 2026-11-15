# Prompt: write impact profile (`impact-profile`)

Section: `## 🌍 Public Domains & Impact Profile`
Owns the gate codes: `bc-line-missing`.

This prompt names who is affected and how badly: public API impact, blast
radius, severity — and whether the change is a backward-compatibility break.

## Inputs this prompt may read

- The public API surface the change touches (signatures, events, statuses).
- The callers and data a failure can reach.
- The `dependency-matrix` rows this change touches — cite them, don't copy.

## Output template

Use exactly this heading — do not rename it:

```markdown
## 🌍 Public Domains & Impact Profile

Public API: <impact on public signatures, events, statuses>
Blast radius: <who and what a failure can reach>
Severity: low | medium | high
BC break: yes | no
```

Rules:

- `BC break: yes` or `BC break: no` is mandatory — without it the section
  fails `bc-line-missing`. The line must match `BC break: yes|no`
  (case-insensitive).
- `yes` means existing callers must change code; `no` means they do not.
- Every package named here must appear in `dependency-matrix` — cite it,
  never restate it.
- Do not restate the problem statement — cite `R#` ids instead.
- Inherits the shared `prompt-preamble.md`: citation rule, stop rule, tier
  rule, and the prohibitions — no invented permalinks, no restating, one
  `When`.

## Worked example

A real, conforming section — the engine accepts this exact content for
`impact-profile` with zero findings.

```markdown
Public API: none — no signatures, events or statuses change.
Blast radius: callers of WorkflowEngine::resolve() with string bodies.
Severity: low.
BC break: no
```