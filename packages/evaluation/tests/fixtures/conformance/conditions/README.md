# Conformance Corpus — Arazzo Simple Conditions

This directory holds a language-agnostic conformance corpus of Arazzo simple
conditions. Every entry carries the condition string, the workflow context the
runtime expressions resolve against, and the expected boolean result.

The corpus is free of PHP concepts so any Arazzo implementation can run it as
the "Act" step of a conformance check.

## File layout

| File | Topic |
|---|---|
| `conditions.json` | Simple conditions, including operand property/index accessors |

## Entry shape

```json
{
  "id": "accessor-index-member-001",
  "description": "zero-based index followed by property de-reference",
  "condition": "$response.body.items[0].id == 1",
  "context": {
    "stepId": "s1",
    "inputs": {},
    "request": { "method": "GET", "url": "https://example.test/items" },
    "response": {
      "statusCode": 200,
      "headers": {},
      "body": {
        "items": [{ "id": 1, "name": "one" }]
      }
    }
  },
  "expected": true,
  "spec": "Arazzo 1.1.0, Criterion Object, Operators: [] Index (0-based), . Property de-reference"
}
```

- `id` — stable, unique within the corpus.
- `description` — human-readable intent.
- `condition` — the raw simple-condition string.
- `context` — the values runtime expressions resolve against:
  - `stepId` — the current step; `$request.*` / `$response.*` refer to it.
  - `inputs` — the workflow input map (`$inputs.*`).
  - `request` — the current step's request (`$request.*`).
  - `response` — the current step's response (`$response.*`), with
    `statusCode`, `headers`, and `body`.
  - Missing maps mean an empty map.
- `expected` — the boolean result of evaluating the condition.
- `spec` — the Arazzo specification text the entry derives from.

## Accessor semantics

The Arazzo 1.1.0 Criterion Object Operators table lists `[]` as "Index
(0-based)" and `.` as "Property de-reference". These apply to the value of a
runtime expression in a simple condition, for example
`$response.body.items[0].id`.

The spec does not define what happens when the accessor cannot be applied.
This corpus pins the following behaviour:

| Case | Result |
|---|---|
| Missing member | `null` |
| Index out of range | `null` |
| Accessor on a non-array value | `null` |

A `null` result then follows the normal Simple Conditions rule: `null` is
falsy, and `null == null` is true.

## Attribution rule

Cases are authored from the Arazzo Specification text
(https://github.com/OAI/Arazzo-Specification). If a case is adapted from
another implementation, record the attribution here and retain its
NOTICE/LICENSE terms.
