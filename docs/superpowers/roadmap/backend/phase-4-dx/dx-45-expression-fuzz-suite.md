# Expression Resolver Fuzz Suite

Category: **dx** · Phase: **4-dx** · Tier: **OSS**
Referenced by: commercial spec threat model (Section 8)

## Problem

`ExpressionResolver` interprets user-authored strings (or OpenAPI-derived strings) against
runtime context. It intentionally has no `eval()` and no PHP execution path — output is
JSONPath / JSON Pointer / literal only. But the parser + evaluator are hand-written; a
malformed expression that trips a stack overflow, infinite loop, or memory blowup would let
a hostile OpenAPI spec (or a hostile catalog import from OAK) DoS the engine or exfiltrate
data via a crafted selector. Threat model item; unproven without adversarial testing.

## Feature

Add `tests/fuzz/expression/`:

- Property-based (`pest-plugin-mutation` + `nikic/iter`) — generate random valid + invalid
  expressions, assert:
  - Parser never throws non-`ExpressionException`.
  - Evaluator never runs > N ms (guard against catastrophic backtracking).
  - Evaluator never allocates > M bytes.
  - Evaluator never reads outside supplied context (JSONPath sandbox verification).
- Corpus-based — seed from OAK's 2000 workflows + a curated hostile corpus (deep nesting,
  Unicode edge cases, JSONPath filter injection attempts, RFC 9535 boundary cases).
- Differential — compare our JSONPath output against `softcreatr/jsonpath` reference for
  every corpus expression; divergence = bug in either.

CI job runs a fixed-seed subset (~30s) per PR; nightly job runs full corpus with randomized
seed for regression drift.

## Acceptance

- Zero crashes (unhandled exceptions, segfaults via extension, allocation failures) across
  10k random inputs + full OAK corpus.
- Zero divergence against `softcreatr/jsonpath` reference on shared subset of RFC 9535.
- Baseline p99 evaluation latency documented and enforced (regression = CI red).

## Out of scope

- Fuzzing the parser / validator (separate suite; different attack surface).
- Fuzzing the executor's HTTP path (integration tests handle it).
