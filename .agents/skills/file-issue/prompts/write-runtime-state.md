# Prompt: write runtime state (`runtime-state`)

Section: `## 🌀 Runtime Execution State Context`
Owns the gate codes: none.

This prompt names where in the execution the behaviour happens: the phase,
step or action in play, the resolved inputs and expressions, and the
environment. It describes the state observed at the moment of the failure — it
never describes the fix, the patch, or what the change will do.

## Inputs this prompt may read

- A trace or log line showing the phase, step and action in play.
- The resolved expressions and input values at that point.
- The environment: versions, feature flags, config keys.

## Output template

Use exactly this heading — do not rename it:

```markdown
## 🌀 Runtime Execution State Context

phase: <phase> · step: <step id> · action: <action> · environment: <one line>
```

Rules:

- This section owns no gate code: nothing here fails the gate on its own.
- It must not describe the fix — no "after the change", no "the patch adds",
  no resolved-by references. Runtime state is observed, not planned.
- Fill in what was true at the moment of the failure; leave out what the fix
  will change.
- Do not restate the problem statement — cite `R#` ids instead.
- Inherits the shared `prompt-preamble.md`: citation rule, stop rule, tier
  rule, and the prohibitions — no invented permalinks, no restating, one
  `When`.

## Worked example

A real, conforming section — the engine accepts this exact content for
`runtime-state` with zero findings.

```markdown
phase: execute · step: "Get definitions" · action: goto · resolved expression: $.paths["/definitions"] · environment: PHP 8.4, no feature flags
```