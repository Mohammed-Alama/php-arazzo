# Prompt: write structural artifacts (`artifacts`)

Section: `## 📄 Structural Artifacts & Raw Inputs`
Owns the gate codes: `artifact-block-missing`.

This prompt captures the smallest document, request or fixture that shows the
behaviour — a minimal Arazzo/OpenAPI document, one request/response pair, a
stack trace, or an exact command. It is the smallest failing document, never a
production one: one endpoint, one workflow, one request, nothing the behaviour
does not depend on.

## Inputs this prompt may read

- The reproduction from `problem-statement` — cite `R#` ids, never repeat
  the statement.
- The exact request/response or document that triggers the behaviour.
- A stack trace or console output from running the reproduction.

## Output template

Use exactly this heading — do not rename it:

````markdown
## 📄 Structural Artifacts & Raw Inputs

<one line: what the block shows, e.g. "The request that triggers the behaviour">

```<lang>
<smallest failing document, request/response or stack trace>
```
````

Rules:

- The section must hold at least one fenced triple-backtick block — without
  one the section fails `artifact-block-missing`. Empty fences count, but a
  real minimal document is better.
- The fenced content is the smallest document that exhibits the behaviour:
  one endpoint, one request, one workflow — never a production spec, a whole
  codebase dump, or a multi-file project.
- Name the artifact kind on the line above the fence (Arazzo document, HTTP
  request/response, stack trace, exact command).
- Do not restate the problem statement here — cite `R#` ids instead.
- Inherits prompt-preamble.md.

## Worked example

A real, conforming section — the engine accepts this exact content for
`artifacts` with zero findings.

````markdown
The request that triggers the behaviour — the smallest document that still fails:

```json
{
  "asyncapi": "2.6.0",
  "id": "unresolved",
  "defaultChannel": "definitions"
}
```

A production spec would name every server and operation; this one names none —
it is the smallest failing document, never a production one.
````