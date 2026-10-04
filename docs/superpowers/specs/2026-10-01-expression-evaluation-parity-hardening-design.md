# Design Specification: Expression & Evaluation Parity and Hardening

- **Status**: Draft
- **Author**: Mohammed Alama & Claude
- **Date**: 2026-10-01
- **Topic**: Close the gaps found when comparing `alama/arazzo-expression` and `alama/arazzo-evaluation` against the
  wider Arazzo ecosystem
- **Builds on**: `2026-09-23-expression-evaluation-package-separation-design.md` (package boundaries are assumed and
  unchanged)

---

## 1. Summary & Motivation

`alama/arazzo-expression` (static: lexer, parser, AST, reference projection) and `alama/arazzo-evaluation` (runtime:
expression evaluation, conditions, criteria, selectors, interpolation) were compared with:

- `@swaggerexpert/arazzo-runtime-expression` (parse-only: `extract`/`test`/`parse`/`interpolate`, CST/AST/XML
  translators, typed parse errors, published ABNF grammar)
- `@swaggerexpert/arazzo-criterion` (simple-criterion parser/validator/evaluator; delegates operands to the package
  above; supports `.member` / `[n]` accessors on operands)
- `pb33f/libopenapi` Go `arazzo/expression` (`Parse`, `Validate`, `Evaluate`, `EvaluateString`, `ParseEmbedded`,
  per-engine criterion caches)
- `strefethen/arazzo-cli` (Rust: simple/regex/XPath/JSONPath criteria, observer events such as `CriterionEvaluated`)

**Conclusion of the comparison:** php-arazzo is ahead on breadth (full pipeline, four criterion types, plugin
registries, reference projection feeding document validation rules). It is behind on **rigor and diagnosability**:
regex-based interpolation, position-less syntax errors, no conformance corpus, re-parsing on every evaluation,
silent criterion failure, and several untested semantic edge cases.

This spec defines the work to close those gaps **without changing package topology or public-facing behavior that
the spec mandates**.

---

## 2. Evidence Base

Findings are labelled by how they were established, per the repo's epistemic rule.

| Label        | Meaning                                                              |
|--------------|----------------------------------------------------------------------|
| `[read]`     | Confirmed by reading the php-arazzo source on 2026-10-01             |
| `[external]` | Taken from a README/pkg.go.dev/search snippet; not checked vs. spec  |
| `[unread]`   | File exists but was not read; the plan begins with a verification step |

Files read: `expression/src/Parser.php`, `expression/src/ExpressionEngine.php`,
`evaluation/src/ExpressionEvaluator.php`, `evaluation/src/Condition/Parser.php`,
`evaluation/src/Condition/ConditionEvaluator.php`, `evaluation/src/CriteriaEvaluator.php`,
`evaluation/src/StringInterpolator.php`, `evaluation/src/JsonPointer.php`.

Not read: both `Lexer` classes, `JsonPathEvaluator`, `SelectorEvaluator`, `PayloadReplacer`, the XPath evaluators, the
plugin classes and registries, `ExpressionEvaluator`'s consumers in `runner`.

---

## 3. Gaps and Requirements

Each gap has an ID referenced by the plan. Priority: **P1** correctness/safety, **P2** quality, **P3** completeness.

### G1 (P1) - Interpolation is regex-based `[read]`

`StringInterpolator` uses `preg_replace_callback('/\{\$([^\}]+)\}/', ...)`. Expression boundaries are decided by a
character class, not by the expression grammar, so any expression whose legal syntax contains `}` is cut short, and
the validator cannot reuse the same splitting logic.

**Requirement R1.** The expression package exposes an embedded-expression splitter that returns ordered
literal/expression segments with source offsets. `StringInterpolator` and document validator rules that inspect
`{$...}` occurrences both use it. Whether `}` is legal inside an expression is decided by the Arazzo ABNF (confirmed
in plan Task 3 step 1), not assumed.

### G2 (P1) - Syntax errors carry no position `[read]`

Every `ExpressionSyntaxException` in `expression/src/Parser.php` is constructed with offset `-1` and an empty
fragment argument.

**Requirement R2.** Syntax errors report the zero-based byte offset of the offending token and the offending
fragment. Offsets are sourced from lexer tokens (Task 2 step 1 verifies `Token` already records them).

### G3 (P1) - No grammar fidelity check `[read]`

The expression parser is hand-written with no published grammar and no shared corpus. There is no automated way to
show it accepts/rejects exactly what the spec's ABNF accepts/rejects.

**Requirement R3a.** The Arazzo runtime-expression grammar, written in ABNF (RFC 5234) as the specification
defines it, is vendored into the repo as a versioned, human-readable artifact. The parser is reviewed against it.

**Requirement R3b.** The vendored ABNF is executable in tests as an oracle (recogniser + generator) so divergence
between the grammar and the hand-written parser is detected mechanically. Every divergence is classified (bug,
intentional leniency, or ABNF ambiguity) and recorded in a register.

**Requirement R3.** A language-agnostic conformance corpus (data files, not PHP) of valid and invalid
expressions, each with an expected outcome and, for valid ones, an expected reference projection. The corpus is
runnable by any implementation as the "Act" step, consistent with the Arrange/Assert fixtures discussed with the
Arazzo maintainers (Conformance Testing, OAI/Arazzo-Specification#448).

### G4 (P2) - Condition operands have no accessors `[read]` + `[external]`

`Condition/Parser::parseOperand()` accepts number, string, ident or a single runtime-expression token. `@swaggerexpert/arazzo-criterion` and
arazzo-cli support property dereference and index access on operands (e.g. `$response.body.items.length > 0`).
The spec section that mandates this was **not** confirmed.

**Requirement R4.** Confirm the spec text first. If accessors are specified, add them to the condition grammar
(`RuntimeExpr` gains an `accessors` list) and navigation to `ConditionEvaluator`. If not specified, record the
decision as an extension and gate it behind an explicit option or drop it.

### G5 (P2) - Comparison semantics unverified `[read]`

Current behavior in `ConditionEvaluator`:

| Behavior                                               | Location            |
|--------------------------------------------------------|---------------------|
| `==`/`!=` on strings is case-insensitive (`strcasecmp`) | `looseEquals`       |
| Numeric strings compare equal to numbers               | `looseEquals`       |
| `<`,`>`,`<=`,`>=` with a non-numeric side return false | `compare`           |
| Truthiness: `''`, `0`, `0.0`, `'0'`, `[]`, `null`, `false` are falsy | `truthy` |
| `&&`/`||` short-circuit                                | `resolve`           |

**Requirement R5.** Each row is checked against the spec text and either pinned by a named test (spec-conformant)
or changed (spec-divergent). No behavior change without a citation of the spec clause in the test docblock.

### G6 (P2) - Re-parse on every evaluation `[read]`

`ExpressionEvaluator::evaluate()` calls `new ExpressionParser()->parse($expression->raw)` each time.
`ConditionEvaluator::evaluate()` re-lexes and re-parses the condition string each time.

**Requirement R6.** Parsed ASTs are cached per engine instance, keyed by raw string, with a **bounded** size.
Bounded matters because Laravel queue workers are long-lived. No static/global cache.

### G7 (P2) - Failures are silent `[read]`

`CriteriaEvaluator` turns `ConditionSyntaxException`, regex context errors, JSONPath/XPath errors, and missing
context into `false` via `catch (\Throwable)`. This is deterministic but the caller cannot tell "criterion
evaluated false" from "criterion could not be evaluated".

**Requirement R7.** An additive detailed API returns, per criterion: passed/failed, a machine-readable reason code
(`evaluated`, `syntax-error`, `context-unresolved`, `selector-error`, `plugin-error`), the criterion, and where
applicable the error message/offset. Existing `bool` methods delegate to it and keep their behavior. Optionally, an
observer interface receives one event per criterion (modelled on arazzo-cli's `CriterionEvaluated`).

### G8 (P1/P2) - JSON Pointer and pointer-on-scalar defects `[read]`

1. `JsonPointer::resolve()` does `explode('/', ltrim($pointer, '/'))`. `ltrim` strips **all** leading slashes, so
   `//a` (RFC 6901: empty-string key, then `a`) resolves as `/a`. `[read]`
2. A pointer that does not start with `/` (and is not empty) is accepted instead of rejected. `[read]`
3. `ExpressionEvaluator` applies a pointer only when the value `is_array || === null`; for a **scalar** value with a
   pointer it returns the scalar unchanged instead of `null`. Affects `InputRef` and `OutputPart`. `[read]`
4. No `-` (past-the-end) handling and no URI-fragment percent-decoding. (Decode/`-` are only needed if the spec
   or a call site requires them; Task 5 decides.)

**Requirement R8.** Pointer resolution follows RFC 6901 exactly for reading; scalar + pointer yields `null`.

### G9 (P3) - Partial reference coverage `[read]`

- `ComponentRef` resolves only `components.parameters`; other component types return `null`.
- `SourceRef` handles sub-paths `url` and `type` only.
- `MessageRef` reads from the current step's HTTP response rather than a message transport.

**Requirement R9.** Enumerate which component types and source sub-paths the spec defines; implement or explicitly
document as unsupported with a typed "unsupported" outcome rather than `null`.

### G10 (P3) - Parser structure `[read]`

`Parser::parse` builds token slices with `array_slice` per rule and validates by token count. This works but makes
error positions (G2) and grammar review harder.

**Requirement R10.** Not a rewrite. Introduce a small token cursor only if G2 needs it; otherwise leave as is.

---

## 4. Design

### 4.1 Embedded-expression splitter (R1)

In `packages/expression`:

```php
namespace Alama\Arazzo\Expression;

final readonly class EmbeddedSegment
{
    public function __construct(
        public bool $isExpression,
        public string $text,     // literal text, or the expression WITHOUT braces
        public int $offset,      // byte offset of the segment start in the input
    ) {}
}

// ExpressionEngineInterface (additive)
/** @return list<EmbeddedSegment> @throws ExpressionSyntaxException on unterminated/invalid embedded expression */
public function parseEmbedded(string $raw): array;
```

- Each expression segment is validated with the same `Parser` used for bare expressions.
- `evaluation/StringInterpolator` consumes `parseEmbedded()` through the interface and resolves expression segments
  via `ExpressionResolverInterface`. Literal segments pass through untouched.
- Null resolves to `''` and non-scalars to JSON, **preserving current behavior** (pinned by existing tests before
  the change).
- Layering: `evaluation` already depends on `expression`; `document` already depends on `expression`. No new edges.

### 4.2 Positioned errors (R2)

`ExpressionSyntaxException` keeps its constructor signature. The parser passes the token offset and the offending
token text where it currently passes `-1` and `''`. Helper `Parser::fail(string $message, string $raw, ?Token $at)`
centralizes this so the 25+ `throw` sites stay uniform.

### 4.3a Vendored ABNF and oracle (R3a, R3b)

**Artifacts**

- `packages/expression/resources/grammar/arazzo-runtime-expression.abnf`: header comment records spec version(s),
  URL and retrieval date. If 1.0.1 and 1.1.0 differ, one file per version (`...-1.0.1.abnf`, `...-1.1.0.abnf`); decided
  after diffing the two in plan Task 1.
- Provenance rule: transcribed **from the specification text**, not copied from another project's grammar file
  (`swaggerexpert` packages are Apache-2.0; copying would require attribution and NOTICE handling).
- A simple-condition grammar (`packages/evaluation/resources/grammar/arazzo-simple-condition.abnf`) only if the
  specification defines one (plan Task 6 checks). If it does not, none is authored as normative; any grammar written
  for our own documentation is marked non-normative.

**Oracle (test support only, never shipped)** in `packages/expression/tests/Support/Abnf/`:

- `AbnfGrammar::parse(string): AbnfGrammar`: the RFC 5234 subset the vendored file uses: rulelist, alternation,
  concatenation, repetition (`*`, `n*m`, `n`), groups, options, quoted strings (case-insensitive per RFC 5234),
  `%x` values (single, ranges, dotted concatenation), RFC 5234 appendix B core rules, and `=/` if the file uses it.
  Unsupported constructs (e.g. prose values) throw a clear error.
- `AbnfRecognizer::matches(string $rule, string $input): bool`: backtracking recogniser over Unicode code points
  with a step limit that fails loudly instead of hanging.
- `AbnfGenerator::generate(string $rule, int $seed, int $maxDepth): string`: seeded, depth-bounded generator of
  valid strings.
- `AbnfMutator`: single-character edits drawn from grammar-relevant characters (`$ . # / ~ { } [ ]`).
- Before writing any of this, time-box a search for an existing maintained PHP ABNF library usable as a dev
  dependency. If one exists it replaces the hand-written oracle.

**Differential test** (`AbnfDifferentialTest`, deterministic seeds; case count via `ARAZZO_ABNF_CASES`, small default):

1. Generated strings for the `expression` rule must be accepted by `Parser` (unless in the register).
2. For mutated strings the oracle and `Parser` must agree on accept/reject (unless in the register).

**Divergence register** `packages/expression/tests/fixtures/conformance/divergences.json`: entries
`{input|pattern, oracle, parser, class: bug|leniency|ambiguity, reason, decision}`. Candidates already visible in
the code, to be checked first: `Parser::parse` optionally consumes the leading `$` (so `inputs.name` parses) `[read]`;
the `$steps.<id>.<output>` shortcut (the code comment cites the official OAI examples) `[read]`; Keyword vs Name
treatment of identifiers; empty JSON Pointer segments.

**Known limit:** an expression's boundary against trailing accessors in conditions is not expressible in a
context-free grammar (noted in the `arazzo-criterion` grammar header `[external]`). The oracle covers the
runtime-expression rule; accessor splitting is covered by the corpus and condition tests.

### 4.3 Conformance corpus (R3)

Location: `packages/expression/tests/fixtures/conformance/expressions/*.json`. One file per topic
(`inputs.json`, `steps.json`, `request-response.json`, `workflows.json`, `sources-components.json`,
`embedded.json`, `invalid.json`). Entry shape:

```json
{
  "id": "steps-output-pointer-001",
  "description": "step output with JSON pointer",
  "expression": "$steps.login.outputs.user#/id",
  "valid": true,
  "projection": { "kind": "StepOutput", "stepId": "login", "name": "user", "pointer": "/id" },
  "spec": "Arazzo 1.0.1, Runtime Expressions"
}
```

Invalid entries carry `"valid": false` and optionally `"errorOffset"`. A single Pest data-driven test loads every
file. Cases are **authored from the specification's ABNF and examples**. Another project's tests are **not**
copied; if cases are adapted from `swaggerexpert` (Apache-2.0) the file records attribution. The format stays free of PHP
concepts so other implementations can consume it.

### 4.4 Condition accessors (R4)

Conditional on the spec check. If adopted: `RuntimeExpr` gains `list<Accessor>` (`Member(string)`,
`Index(int)`); the condition `Lexer` splits the operand token into base + accessors (base still parsed by the
expression `Parser`); `ConditionEvaluator::operandValue()` applies accessors after evaluation.

### 4.5 Bounded AST cache (R6)

`final class AstCache { public function __construct(private int $max = 256) }` with `get(string): ?ExpressionAst`,
`put(string, ExpressionAst): void`, simple FIFO/LRU eviction. One instance owned by each `ExpressionEvaluator` (and a
parsed-condition cache owned by each `ConditionEvaluator`). Only **successful** parses are cached; syntax errors are
re-thrown fresh. `ExpressionEvaluator` receives the `Parser` by constructor (defaulting to `new Parser()`) instead
of constructing one per call.

### 4.6 Criterion diagnostics (R7)

```php
enum CriterionOutcome: string { case Passed = 'passed'; case Failed = 'failed'; case Error = 'error'; }
enum CriterionReason: string { case Evaluated; case SyntaxError; case ContextUnresolved; case SelectorError; case PluginError; }

final readonly class CriterionResult
{
    public function __construct(
        public SuccessCriterion $criterion,
        public CriterionOutcome $outcome,
        public CriterionReason $reason,
        public ?string $message = null,
    ) {}
}
```

- `CriteriaEvaluator::evaluateCriteriaDetailed(...): list<CriterionResult>` (short-circuits like today: stops at
  first non-pass, so the list ends with that result).
- `evaluateCriteria()` returns `$results === [] || last outcome is Passed`. Behavior identical to today.
- Optional `CriterionObserverInterface::criterionEvaluated(CriterionResult $r): void`, injected nullable.
  Wiring this through the runner (telemetry) is **out of scope** here and only noted in the plan as a follow-up.

### 4.7 JSON Pointer hardening (R8)

- `JsonPointer::resolve()` splits on `/` after stripping **exactly one** leading `/`; rejects non-empty pointers
  not starting with `/` (returns `null`, consistent with "not found"; a separate `JsonPointer::isValid()` is added
  for validator use).
- `ExpressionEvaluator` applies a pointer to arrays/objects only. Scalar + pointer returns `null`; `null` + pointer
  returns `null`.
- Tilde unescape order (`~1` then `~0`) is already correct and gets an explicit test.
- `-` handling and percent-decoding: decided in plan Task 5 after checking call sites and spec.

### 4.8 Reference coverage (R9)

Introduce a typed unsupported outcome only if Task 8's enumeration finds spec-defined forms we do not resolve.
Otherwise add tests that pin the current `null` behavior and document it in `docs/architecture/04-*`.

---

## 5. Compatibility & Risk

| Risk                                                        | Mitigation                                                        |
|-------------------------------------------------------------|-------------------------------------------------------------------|
| Behavior change in interpolation/condition semantics        | Characterization tests land **before** each change (plan Task 0/1) |
| Pointer fix changes outputs for malformed pointers          | Only `//a`-style and scalar+pointer cases change; both were wrong |
| Cache retains memory in queue workers                       | Bounded, per-instance, successful parses only                     |
| Spec check contradicts a proposed change (G4, G5)           | Decision recorded; change dropped or flagged, not forced          |
| Corpus drifts from spec versions (1.0.1 vs 1.1.0)           | Each entry names its spec version; version-specific entries tagged |

Public surface changes are **additive** (`parseEmbedded`, `evaluateCriteriaDetailed`, `CriterionResult`,
`CriterionObserverInterface`, `JsonPointer::isValid`). No interface method is removed or re-typed.

---

## 6. Architectural Fitness Functions

- New: the vendored ABNF file exists, parses with `AbnfGrammar`, and every rule it references is defined (guards
  against a truncated or hand-edited grammar).

- `expression` still must not use `Alama\Arazzo\Evaluation`, `Document`, or `Runner` (existing arch tests).
- New: `AstCache` is `final` and has no static properties (Pest arch: `->not->toUse` global state is approximated by a
  reflection test asserting no static props).
- New: `StringInterpolator` no longer references `preg_replace_callback` (source-grep test, guards against regressing
  to regex splitting).

---

## 7. Non-Goals

- Replacing the hand-written parser with a parser **generated** from the ABNF. Considered: it would give grammar
  fidelity by construction, but the AST and reference projection would need re-mapping, and I am not aware of a
  mature PHP generator (unverified; the oracle task includes a time-boxed search). The vendored ABNF with the oracle
  (R3a/R3b) and the corpus (R3) gives most of the assurance at a fraction of the cost. Revisit if they keep finding
  divergences. Using the ABNF as a vendored artifact and test oracle **is** in scope.
- CST/XML translators like swaggerexpert's. No demonstrated consumer.
- New packages or changes to package dependency edges.
- Wiring criterion observers into runner telemetry.
- Non-spec extensions to the expression grammar.

---

## 8. Success Criteria

1. Conformance corpus runs green and is consumable without PHP.
2. Every `ExpressionSyntaxException` thrown by the parser has a real offset (asserted in a test that scans the
   corpus's invalid entries).
3. `StringInterpolator` has no regex splitting; behavior pinned by pre-existing characterization tests.
4. Repeated evaluation of the same expression parses once (asserted with a counting parser double).
5. `evaluateCriteriaDetailed` distinguishes `Failed` from `Error` for each criterion type; `evaluateCriteria` results unchanged
   across the existing suite.
6. RFC 6901 pointer test table passes, including `//a`, `/`, `~0`/`~1`, and scalar+pointer.
7. Each G5 row has a spec citation in a test docblock.
8. `composer run test-expression`, `test-evaluation`, `test-document`, and both `analyse-*` scripts are green.
9. The vendored ABNF is committed with spec version, URL and provenance in its header, and parses with the oracle.
10. The differential test runs green with every divergence either fixed or recorded in `divergences.json` with a
    class and a reason.
