# Prompt: write feature scope (`feature-scope`)

Section: `## 🎯 Feature Scope & Prior Art`
Owns the gate codes: `non-goals-missing` and the `D#` shape.

This prompt fixes the boundary of the change: goals, non-goals, prior art and
the proposed interface. It never restates the problem statement — it cites
`R#` ids and derives scope from them. An unresolved design decision is not a
scenario and not an assumption: it becomes a `D#` line here (the stop rule),
and every scenario that depends on it is marked `blocked by D#`.

## Inputs this prompt may read

- `R#` ids from `problem-statement` (each goal traces to at least one `R#`).
- Prior art: earlier approaches in this repo or their issues, cited by
  number or permalink.
- A proposed interface sketch: class, method, signature.

## Output template

Use exactly this heading — do not rename it:

```markdown
## 🎯 Feature Scope & Prior Art

Goals: <what this change achieves>
Non-goals: <what it explicitly does not achieve>
Prior art: <earlier approaches and why they are not reused>
Proposed interface: <class/method/signature sketch>

[D#. <open question>? Options: <a>, <b>. Default: <a>.]
```

Rules:

- `Non-goals:` must carry text on the same line, or head a non-empty bullet
  list that runs until the next non-bullet line — otherwise the section fails
  `non-goals-missing`.
- Every `D#` line follows the stop rule shape:
  `D#. <question>? Options: <a>, <b>. Default: <a>.` — question, two or more
  options, and a default. This is the `D#` shape this prompt owns.
- Cite `R#` ids instead of repeating requirements; never copy the problem
  statement into this section.
- No `S#` scenarios here — those belong to acceptance criteria.
- Inherits the shared `prompt-preamble.md`: citation rule, stop rule
  (`D#` with options and a default), tier rule, and the prohibitions — no
  invented permalinks, no restating, one `When`.

## Worked example

A real, conforming section — the engine accepts this exact content for
`feature-scope` with zero findings.

```markdown
Goals: add a JsonPath resolver to the engine.
Non-goals: support XPath, support XML bodies, or validate expressions statically.
Prior art: the XPath support dropped in 1.2 (issue #44).
Proposed interface: Engine::resolve(string $expression, string $body): mixed

D1. Should resolve() throw or return null on a missing node? Options: throw, return null. Default: throw.
```