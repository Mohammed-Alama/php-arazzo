# Conformance Corpus — Arazzo Runtime Expressions

This directory holds a language-agnostic conformance corpus of Arazzo runtime
expressions, one JSON file per topic. Every entry carries an expected outcome
and — for valid entries — the expected reference projection produced by
`Parser::projectReferences()`.

The corpus is free of PHP concepts so any Arazzo implementation can run it as
the "Act" step of a conformance check.

## File layout

| File | Topic |
|---|---|
| `inputs.json` | `$inputs.*` references |
| `steps.json` | `$steps.*` references (incl. shortcut) |
| `request-response.json` | `$request.*` / `$response.*` (current step) |
| `workflows.json` | `$workflows.*` references |
| `sources-components.json` | `$sourceDescriptions.*` / `$components.*` |
| `embedded.json` | `{$...}` / `${...}` embedded forms |
| `invalid.json` | Invalid expressions that must throw |

## Entry shape

```json
{
  "id": "steps-output-pointer-001",
  "description": "step output with JSON pointer",
  "expression": "$steps.login.outputs.user#/id",
  "valid": true,
  "projection": {
    "kind": "Step",
    "target": "login",
    "part": "outputs",
    "name": "user",
    "httpPart": null,
    "jsonPointer": "/id"
  },
  "spec": "Arazzo 1.0.1, Runtime Expressions"
}
```

- `id` — stable, unique within the corpus.
- `description` — human-readable intent.
- `expression` — the raw runtime expression string (no surrounding quotes beyond JSON).
- `valid` — whether the expression is accepted by the parser.
- `projection` — present only when `valid` is true. The expected
  `ExpressionReference` projection as a plain object:
  - `kind` — reference kind (`Input`, `Output`, `Step`, `Workflow`, `Source`,
    `Component`, `Message`, `Self`, `HttpMeta`).
  - `target` — the referenced id/name (input name, step id, workflow id, …).
  - `part` — `inputs`/`outputs`/`request`/`response`/`header`/`payload`, or null.
  - `name` — member/header/parameter name, or null.
  - `httpPart` — `url`/`method`/`statusCode`/`body`/`header`/`query`/`path`, or null.
  - `jsonPointer` — JSON Pointer suffix (RFC 6901), or null.
- `spec` — the Arazzo specification version this entry targets.

## Spec-version tagging

Entries are tagged with the spec version they derive from. Patch versions
(1.0.0 → 1.0.1) share a feature set per §5.1 "Versions"; minor versions
(1.0.x → 1.1.0) may introduce new roots (`$message.*`, embedded forms) and are
tagged accordingly.

## Invalid forms

Entries in `invalid.json` carry `"valid": false` and no `projection`. Each must
cause `Parser::parse()` to throw `ExpressionSyntaxException`.

## Attribution rule

Cases are authored from the Arazzo Specification text and ABNF (vendored in
`packages/expression/resources/grammar/`). If a case is adapted from
`swaggerexpert/arazzo-runtime-expression` (Apache-2.0), record the attribution
here and retain its NOTICE/LICENSE terms.

## Known leniencies (parser accepts more than the spec strictly requires)

These are intentional leniencies, not corpus errors:

- `$inputs.name#` — a pointer marker (`#`) with an empty path is accepted and
  yields a null/empty JSON Pointer. The spec requires `json-pointer` to be
  `*( "/" reference-token )`, which permits zero tokens, so an empty pointer
  after `#` is technically well-formed.
- `$response.body#/a~0b~1c` — `~0`/`~1` escape sequences are stored raw
  (`/a~0b~1c`) rather than unescaped to `/a/b~c`. The parser does not apply
  RFC 6901 percent-encoding-style unescape. Recorded here as current behavior;
  a follow-up may tighten this to strict RFC 6901.
