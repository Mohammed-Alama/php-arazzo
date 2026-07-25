# Arazzo 1.1.0 Spec Support

Category: **core** · Phase: **0-foundation** · Tier: **OSS**
Depends on: parser + validator + expression resolver (shipped)

## Problem

The engine currently parses/validates only `arazzo: "1.0.0"`. The 1.1.0 spec release
introduces significant additions the current DTOs / enums / validator do not model:

- **AsyncAPI source descriptions** — `sourceDescriptions[*].type: "asyncapi"`, plus per-step
  `action: send | receive`, `channelPath`, `correlationId`. `SourceType` enum has no
  `Asyncapi` case; `ParameterIn` has no `Querystring` case.
- **Selector Object** — replaces bare string templating for outputs / parameters with a
  structured `{context, selector, type}` shape. The `type` is a pinned-version Expression
  Type Object (`jsonpath`+`rfc9535`, `xpath`+`xpath-10..31`, `jsonpointer`+`rfc6901`).
- **Sub-workflow composition** on Success/Failure Action Objects — `workflowId` +
  `parameters` map, chained without leaving the current run.
- **`in: querystring`** parameter location — distinct from `query` (whole-string binding vs.
  per-key binding).
- **Root-level `$self`** URI — self-reference for cross-document links.

A fixture at `tests/fixtures/parser/arazzo-1.1-cross-protocol-saga.yaml` demonstrates every
one of these. It is deliberately kept out of the passing parser test suite until this stub
lands — the parser hard-rejects `"1.1.0"` today.

## Feature

Broken down by subsystem:

### Parser + DTOs

- Extend the version guard to accept `1.0.0` and `1.1.0`.
- Add `SourceType::Asyncapi`, `ParameterIn::Querystring`.
- Add `Selector` DTO (readonly value object) + `ExpressionType` enum with pinned versions.
- Change output / parameter fields from `string` → `string|Selector` (union) with a
  compatibility helper that resolves either into the runtime evaluator.
- Add `SuccessAction::$workflowId` + `SuccessAction::$parameters` (same for `FailureAction`).
- Add root-level `$self` field to `ArazzoDocument`.

### Validator (39 rules)

- New rules: selector requires a supported `type`; sub-workflow composition requires the
  target workflow to exist in this document or a resolved source; `in: querystring` is only
  valid on GET-shape operations.
- Existing rules that assume string-only expressions must gain a "or Selector" branch.

### Expression Resolver

- Route Selector-shaped inputs through `ExpressionType`-specific evaluators (JSONPath /
  XPath / JSON Pointer). Bare-string legacy path continues to work for 1.0.0 docs.
- `xpath` support is new — add `xpath` PHP extension as a suggested composer dep, with a
  runtime probe + descriptive error if the doc uses XPath and the extension is missing.

### Executor

- AsyncAPI: `AsyncApiStepExecutor` (already shipped) grows a `1.1.0` code path for
  `channelPath` + `correlationId` fields that the resolver did not populate under 1.0.0.
- Sub-workflow composition: the executor forks a child `WorkflowContext` for the target
  workflow, passing bound parameters; result feeds back into parent decision point.

## Why phase 0-foundation

Every non-trivial roadmap item (`ai-10` agent routing, `exec-07` saga, `tenant-09` context
bridges) references 1.1.0 constructs — Selector, sub-workflow composition, AsyncAPI. Building
those against a 1.0.0-only substrate means re-doing them once 1.1.0 lands. Get the substrate
right first, then everything above it is 1.1.0-native from day one.

## Acceptance

- `tests/fixtures/parser/arazzo-1.1-cross-protocol-saga.yaml` parses green and validates
  through the full 39-rule (+ new-rule) pipeline.
- End-to-end execution of the fixture against a mock AsyncAPI broker + HTTP server succeeds
  with correlation resume and sub-workflow composition both exercised.
- 1.0.0 fixtures continue to pass without modification — bare-string expressions still work.
- Public API additions are SemVer-minor safe (only new fields / new enum cases / new DTOs;
  no removals from existing signatures).

## Out of scope

- Migrating existing 1.0.0 fixtures to 1.1.0 shapes — leave alone; 1.0.0 stays supported.
- Reverse translation (`1.1.0` → `1.0.0`) — no consumer for it.

## References

- Arazzo 1.1.0 release notes (upstream OAI/Arazzo repo).
- Cross-cutting delta notes previously in `roadmap/ROADMAP.md` "Arazzo 1.1.0" section.
