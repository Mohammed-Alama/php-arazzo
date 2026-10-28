# Vendored Arazzo Runtime-Expression ABNF grammars

This directory holds the Arazzo runtime-expression grammar, transcribed from
the Arazzo Specification (RFC 5234 ABNF) as a versioned, human-readable
artifact. It is the arbiter of "what the spec accepts" for the hand-written
parser in `packages/expression`.

## Files

| File | Spec section | Source |
|---|---|---|
| `arazzo-runtime-expression-1.0.1.abnf` | Runtime Expressions (§5.9) | https://github.com/OAI/Arazzo-Specification/blob/main/versions/1.0.1.md |
| `arazzo-runtime-expression-1.1.0.abnf` | Runtime Expressions (§5.9) | https://github.com/OAI/Arazzo-Specification/blob/main/versions/1.1.0.md |

## Spec-version tagging

Each `.abnf` file carries a provenance header comment recording:

- the spec version(s) it covers,
- the source URL,
- the retrieval date.

When a new spec version is published, add a new file here (or version-tag the
existing one if the grammar is unchanged) and update this README. Patch
versions (e.g. 1.0.0 → 1.0.1) do not change the feature set per
§5.1 ("Versions"), so they share the grammar of their minor version. Minor
versions (e.g. 1.0.x → 1.1.0) may change the grammar and get their own file.

## Deltas: 1.0.1 vs 1.1.0

The two grammars differ (one file per version, per acceptance criteria):

- 1.1.0 adds the `$message.` source root (AsyncAPI message payload).
- 1.1.0's `$steps.` uses the `step-id.outputs.` form with `identifier-strict`
  step IDs; 1.0.1 uses a bare `name`.
- 1.1.0's `$workflows.` uses the `workflow-id.field.field-name` form; 1.0.1
  uses a bare `name`.
- 1.1.0's `$sourceDescriptions.` uses `source-name.source-reference-id`
  (`1*CHAR`); 1.0.1 uses a bare `name`.
- 1.1.0's `$components.` uses `component-type.component-name` where
  `component-type` is `parameters` / `successActions` / `failureActions`;
  1.0.1 exposes only `$components.name` and `$components.parameters.parameter-name`.
- 1.1.0 adds `identifier`, `identifier-strict`, and `name` rules plus the
  `embedded-expression` / `expression-string` rules for `{$...}` templates.
- 1.1.0's JSON Pointer `CHAR` excludes `{` / `}` for unambiguous embedded
  expression parsing; 1.0.1 does not.

## Provenance rule

The grammars are transcribed from the Arazzo Specification text, not copied
from another project's grammar file. The `swaggerexpert/arazzo-runtime-expression`
package (Apache-2.0) also vendors this grammar; we do not use it as a source
to avoid attribution/NOTICE obligations. If any case is adapted from that
project, record the attribution here.

## Tests

`packages/expression/tests/Conformance/ExpressionAbnfFileTest.php` asserts each
file's header (spec version, source URL, retrieval date) is present. Once the
ABNF oracle lands (Story B / Task 1b), it additionally asserts each file parses
with `AbnfGrammar` and that every rule it references is defined.
