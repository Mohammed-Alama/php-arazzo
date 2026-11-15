# Prompt: write acceptance criteria (`acceptance-criteria`)

Section: `## 🧪 Acceptance Criteria & Behavioral Specification`
Owns the gate codes: `scenario-shape`, `scenario-no-test`,
`requirement-uncovered`, `quality-coverage-missing`, `vague-then`,
`checklist-unverifiable`.

This prompt converts the `R#` requirements of `problem-statement` and the
open `D#` decisions of `feature-scope` into checkable scenarios. It never
restates the problem statement — it cites `R#` ids and derives `S#`
scenarios from them.

## Inputs this prompt may read

- `R#` lines from `problem-statement` (each requirement gets at least one
  `S#` scenario referencing it)
- `D#` decisions from `feature-scope` (a scenario blocked by an open
  decision is marked `blocked by D#` and gets no `Test:` line yet)
- The package's test class names (for the `Test:` lines)
- The nine quality questions (one row each in the coverage table)

## Output template

Use exactly this heading — do not rename it:

```markdown
## 🧪 Acceptance Criteria & Behavioral Specification

S1 (R1, Reliable): <one-line scenario title>
  Given <starting state>
  When <the action under test>
  Then <a concrete, measurable outcome>
  Test: ClassTest::it_does_x

[S2 (R1, Safe): ...]

C1. <invariant or constraint that outlives one scenario>
  Check: <how the check is performed>

| Quality | Covered by | Note |
| --- | --- | --- |
| Efficient | S1 | <why> |
| Flexible | S1 | <why> |
| Maintainable | S1 | <why> |
| Operable | S1 | <why> |
| Reliable | S1 | <why> |
| Safe | S1 | <why> |
| Secure | N/A | <non-empty reason> |
| Suitable | S1 | <why> |
| Usable | S1 | <why> |
```

Rules:

- The `S#` header shape is `S<n> (<refs>, <quality>): <title>` — refs are
  `R#` ids from `problem-statement`, and `<quality>` is one of the nine
  table names.
- Each block is Given → When → Then: at least one `Given`, exactly one
  `When`, at least one `Then`, first occurrences in that order
  (`scenario-shape`). Two choices are two scenarios, or a `D#`.
- Every block carries `Test: ClassTest::it_does_x` naming a test that will
  exist (`scenario-no-test`).
- A `Then`/`And` step using a vague qualifier must also name a concrete
  outcome — exception class, return value, event, state field or count.
  "Works correctly", "as expected", "gracefully", "properly" alone fail
  (`vague-then`).
- Every `R#` from the problem statement is referenced by at least one `S#`
  header (`requirement-uncovered`).
- The table names all nine qualities; `Covered by` is `S#`, `C#` or `N/A`,
  and every `N/A` row carries a non-empty reason
  (`quality-coverage-missing`).
- Each `C#` constraint carries a `Check:` line.
- On `kind:chore` and `kind:release` every `- [ ]` / `- [x]` checklist item
  names a backticked command or token or carries a `https://` URL — an item
  naming neither fails `checklist-unverifiable`.
- Inherits the shared `prompt-preamble.md`: citation rule, stop rule
  (`D#` with options and a default), tier rule (no tier without evidence;
  `T4` blocks `ready-for-agent`), and the prohibitions — no invented
  permalinks, no `Verified:` without reading the pinned lines, no "works
  correctly", no restating, one `When`.

## Worked example

A real, conforming section — the engine accepts this exact content for
`acceptance-criteria` with zero findings.

```markdown
S1 (R1, Reliable): the resolver rejects a string body
  Given a JsonPath expression over a JSON string body
  When the expression resolves
  Then it throws UnresolvedWorkflowException
  Test: WorkflowEngineTest::it_rejects_unknown_body

C1. The engine never silently returns null for a missing node.
  Check: every resolve() path returns a value or throws an exception.

| Quality | Covered by | Note |
| --- | --- | --- |
| Efficient | S1 | single traversal of the body |
| Flexible | S1 | any JsonPath expression |
| Maintainable | S1 | one scenario per rule |
| Operable | S1 | the exception names the failure |
| Reliable | S1 | throws UnresolvedWorkflowException |
| Safe | C1 | no silent fallback |
| Secure | N/A | no untrusted input surface |
| Suitable | S1 | matches the reported symptom |
| Usable | C1 | the test names the call site |
```