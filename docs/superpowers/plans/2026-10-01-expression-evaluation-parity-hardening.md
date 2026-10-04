# Expression & Evaluation Parity and Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:
> executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the rigor and diagnosability gaps G1-G10 between `alama/arazzo-expression` /
`alama/arazzo-evaluation` and the wider Arazzo ecosystem: positioned syntax errors, a language-agnostic conformance
corpus, grammar-based embedded-expression splitting, bounded AST caching, RFC 6901-exact JSON Pointer reads,
verified comparison semantics, optional condition accessors, and per-criterion diagnostics.

**Architecture:** Package topology from `2026-09-23-expression-evaluation-package-separation` is unchanged. Parse-side
work (corpus, positioned errors, `parseEmbedded`, AST cache for expressions) lands in `packages/expression`.
Runtime work (interpolator, JSON Pointer, condition cache and semantics, criterion diagnostics) lands in
`packages/evaluation`. All interface changes are **additive**.

**Tech Stack:** PHP ^8.4, Pest v5, PHPStan level max (`packages/expression/phpstan.neon.dist`,
`packages/evaluation/phpstan.neon.dist`), Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-10-01-expression-evaluation-parity-hardening-design.md`

## Global Constraints

- **Characterize before changing.** Every task that alters behavior first adds tests pinning the current behavior
  (green), then changes code, then adjusts only the tests the spec says must change. A changed expectation needs a
  spec citation in the test docblock.
- `declare(strict_types=1)` everywhere. Source comments minimal; docblocks explain spec nuance only.
- No new composer `require` entries. No change to package dependency edges (existing Pest arch tests must stay green).
- Per task: touched packages' suites green (`composer run test-expression`, `composer run test-evaluation`; run the
  `document` and `runner` suites when their consumers change) and `composer run analyse-expression` /
  `composer run analyse-evaluation` clean. **Verify script names against the root `composer.json` in Task 0**; do not
  assume.
- `--filter` runs from the repo root: `vendor/bin/pest packages/{expression,evaluation}/tests --filter "<name>"`.
- Anything labelled `[unread]` in the spec is read and summarized in the task's first step before code is written.
- **ABNF provenance:** grammar files are transcribed from the Arazzo specification text and carry spec version, URL
  and retrieval date in a header comment. Do not copy another project's grammar file. The ABNF and oracle are the
  arbiter of "what the spec accepts"; the hand-written parser follows them, and any intentional difference is
  recorded in `divergences.json`, never left implicit.
- One commit per task, Conventional Commits (`test:`, `fix:`, `feat:`, `refactor:`).
- If a spec check (Tasks 3, 6) contradicts a proposed change, **stop, record the finding in the spec's section for
  that gap, and skip the change.** Do not force it.

---

### Task 0: Baseline and verification of unread code

**Files:** none modified (notes go in the PR description / spec Evidence Base if findings differ).

- [ ] Read root `composer.json` scripts; record the real names for test/analyse per package.
- [ ] Run `test-expression`, `test-evaluation`, `test-document`, `test-runner` and both analyse scripts; record the
      baseline (all green, or list pre-existing failures so they are not attributed to this work).
- [ ] Read `packages/expression/src/Lexer.php`, `Data/Token.php`, `Exceptions/ExpressionSyntaxException.php`
      (confirm whether `Token` records an offset and what the exception's 3rd/4th/5th constructor args mean).
- [ ] Read `packages/evaluation/src/Condition/Lexer.php`, `Condition/Token.php`.
- [ ] Read `packages/evaluation/src/SelectorEvaluator.php`, `JsonPathEvaluator.php`, `PayloadReplacer.php` for any
      use of `JsonPointer` or `StringInterpolator` (these are the call sites Task 3 and Task 5 must not break).
- [ ] Grep all call sites: `grep -rn "StringInterpolator\|JsonPointer::resolve\|->interpolate(" packages/*/src`.
      List them in the task notes.
- [ ] Update the spec's Evidence Base: move `[unread]` files to `[read]`; amend any gap whose premise changed.

**Done when:** baseline recorded; spec Evidence Base accurate.

---

### Task 1: Vendored ABNF and conformance corpus (G3, R3a)

**Additional files (ABNF):** `packages/expression/resources/grammar/arazzo-runtime-expression.abnf` (one file per
spec version if they differ), `packages/expression/resources/grammar/README.md` (provenance, how to update).

**Files:**

- Create: `packages/expression/tests/fixtures/conformance/expressions/{inputs,steps,request-response,workflows,sources-components,embedded,invalid}.json`
- Create: `packages/expression/tests/fixtures/conformance/README.md` (format, spec-version tagging, attribution rule)
- Create: `packages/expression/tests/Conformance/ExpressionCorpusTest.php`

- [ ] Transcribe the runtime-expression ABNF from the spec into the `.abnf` file with the header comment (spec
      version, URL, retrieval date). Keep the spec's rule names. If 1.0.1 and 1.1.0 differ, diff them, keep one file
      per version, and list the differences in the README.
- [ ] Add `ExpressionAbnfFileTest`: file exists, header has version/URL/date, and (once Task 1b lands) parses
      with `AbnfGrammar` with no undefined rule references. Until 1b, assert only the first two.
- [ ] Fetch the Arazzo specification's Runtime Expressions section and ABNF for **1.0.1** (and note deltas in **1.1.0**
      if it is published); save the spec URL and version in the corpus README.
- [ ] Author entries from the ABNF and the spec's examples, using the entry shape in spec section 4.3. Cover at minimum: every
      root (`$url`, `$method`, `$statusCode`, `$request.*`, `$response.*`, `$inputs.*`, `$outputs.*`, `$steps.*`,
      `$workflows.*`, `$sourceDescriptions.*`, `$components.*`, `$self`, `$message.*`), JSON Pointer suffixes,
      `~0`/`~1` escapes, the `$steps.<id>.<output>` shortcut, header/query/path names, and at least 25 invalid forms
      (empty, `$` alone, unknown root, trailing dot, missing name, bad pointer, extra tokens).
- [ ] If adapting any case from `swaggerexpert/arazzo-runtime-expression` (Apache-2.0), check its NOTICE/licence
      terms and record attribution in the README. Otherwise author independently.
- [ ] Write `ExpressionCorpusTest` (Pest dataset over all files): for `valid: true`, `Parser::parse` succeeds and
      `projectReferences` matches `projection`; for `valid: false`, it throws `ExpressionSyntaxException`.
- [ ] Run: all cases that fail are **triaged**, not silently fixed. For each failure decide: parser bug (fix in its own
      commit with the corpus entry as the failing test), corpus error (fix the entry), or spec ambiguity (tag
      `"status": "ambiguous"` and skip with a reason).
- [ ] Commit: `test(expression): add language-agnostic conformance corpus`.

**Done when:** ABNF committed with provenance header; corpus committed, test green, every skipped entry has a
written reason.

---

### Task 1b: ABNF oracle and differential tests (G3, R3b)

**Files:**

- Create: `packages/expression/tests/Support/Abnf/{AbnfGrammar,AbnfRecognizer,AbnfGenerator,AbnfMutator}.php`
- Create: `packages/expression/tests/Conformance/AbnfOracleTest.php` (oracle self-tests)
- Create: `packages/expression/tests/Conformance/AbnfDifferentialTest.php`
- Create: `packages/expression/tests/fixtures/conformance/divergences.json`

- [ ] Time-boxed (about 1 hour) search for an existing maintained PHP ABNF parser/generator usable as a dev
      dependency (Packagist, GitHub). If one fits, use it, skip the hand-written steps below, and record the choice.
      Otherwise continue.
- [ ] Write the **oracle self-tests first**, on tiny inline grammars: repetition bounds (`*`, `n*m`, `n`),
      case-insensitive quoted strings, `%x` single/range/dotted values, alternation with backtracking, groups/options, core
      rules, and `=/` if the vendored file uses it. Include RFC 5234's own examples where practical.
- [ ] Implement `AbnfGrammar::parse`, supporting exactly the subset the vendored file uses. Anything else throws a
      descriptive exception (no silent misparse).
- [ ] Implement `AbnfRecognizer::matches` over Unicode code points (not bytes) with a step limit that throws on
      exhaustion rather than hanging.
- [ ] Implement `AbnfGenerator` (seeded, depth-bounded). Self-test: 1,000 seeds over the `expression` rule are
      all accepted by the recogniser.
- [ ] Implement `AbnfMutator` (single-character insert/delete/replace from `$ . # / ~ { } [ ]` plus a few letters).
- [ ] Point `ExpressionAbnfFileTest` (Task 1) at `AbnfGrammar` for the undefined-rule check.
- [ ] Write `AbnfDifferentialTest` per spec 4.3a: generated strings must be accepted by `Parser`; mutated strings
      must get the same accept/reject from oracle and `Parser`. Fixed seeds. Case count from `ARAZZO_ABNF_CASES`
      (default 500; run with 20,000 locally before finishing the task).
- [ ] **Triage every divergence**; none may be left unclassified. Check first: the `Parser` accepting a missing
      leading `$` (`inputs.name`), the `$steps.<id>.<output>` shortcut, Keyword vs Name handling, empty pointer
      segments, allowed characters in names. For each:
  - `bug` (parser wrong): add a failing corpus entry, fix in its own commit.
  - `leniency` (parser intentionally broader): keep, record reason in `divergences.json`. If the lenient form is
    produced by document authors in the wild, say so; if not, decide whether to make the parser strict.
  - `ambiguity` (spec unclear): record, and note it as a candidate question for the Arazzo specification
    maintainers.
- [ ] Make the test fail when `divergences.json` contains an entry that **no longer diverges** (stale entries must be
      removed).
- [ ] Commit: `test(expression): add ABNF oracle and differential tests`.

**Done when:** differential test green at the default and at 20,000 cases, register has no stale or unclassified
entries, and the ABNF file parses cleanly with the oracle.

---

### Task 2: Positioned syntax errors (G2, G10)

**Files:**

- Modify: `packages/expression/src/Parser.php`
- Modify: `packages/expression/src/Lexer.php` / `Data/Token.php` (only if tokens lack offsets)
- Test: `packages/expression/tests/Expression/ParserErrorPositionTest.php`

- [ ] Write characterization tests asserting the **current** message text for 10 representative failures (so message
      changes are deliberate).
- [ ] If `Token` has no byte offset, add `public int $offset` populated by `Lexer::tokenize`; update `Token`
      construction sites only.
- [ ] Add `private function fail(string $message, string $raw, ?Token $at = null): never` to `Parser` returning an
      `ExpressionSyntaxException` with `$at?->offset ?? -1` and `$at?->value ?? ''` using the exception's real
      constructor argument order (confirmed in Task 0).
- [ ] Replace every `throw new ExpressionSyntaxException(..., -1, '', 'expr.syntax')` with `fail(...)`, passing the
      most specific offending token (the token that failed the check, or the first unexpected token).
- [ ] Extend the corpus: `"errorOffset"` for at least 15 invalid entries; make `ExpressionCorpusTest` assert it when
      present.
- [ ] Add the success-criterion-2 test: every invalid corpus entry yields `offset >= 0` (the end-of-input case uses
      `strlen($raw)`).
- [ ] Do **not** introduce a token cursor unless `fail()` cannot find the right token with `array_slice` indexes;
      if it is needed, do it as a separate refactor commit first.
- [ ] Commit: `feat(expression): report token offsets in syntax errors`.

**Done when:** no `-1` offset is thrown for a token-attributable error; messages unchanged unless a test says so.

---

### Task 3: Embedded-expression splitter and interpolator (G1)

**Files:**

- Create: `packages/expression/src/Data/EmbeddedSegment.php`
- Modify: `packages/expression/src/Parser.php` (or new `EmbeddedParser.php`) and `ExpressionEngine.php`
- Modify: `packages/expression/src/Interfaces/ExpressionEngineInterface.php` (additive `parseEmbedded`)
- Modify: `packages/evaluation/src/StringInterpolator.php`
- Modify: `packages/document/src/Validator/Rules/*` **only** where a rule scans `{$...}` with its own regex
  (found in Task 0 grep)
- Test: `packages/expression/tests/Expression/EmbeddedParserTest.php`,
  `packages/evaluation/tests/StringInterpolatorTest.php`, corpus `embedded.json`

- [ ] **Spec check:** read the vendored ABNF (Task 1) for `embedded-expression`/`expression-string`. If the
      grammar has no rule for embedded expressions, read the spec prose instead and record that the rule is prose-only. Write down, in
      the test docblock, whether `}` may appear inside an expression and how a JSON Pointer with `}` is treated. This
      fixes the splitting rule; do not guess.
- [ ] Characterization tests for `StringInterpolator` **against the current regex implementation**: null -> `''`,
      scalar -> string, array -> JSON, multiple expressions, adjacent expressions, no expressions, unterminated
      `{$`, literal `{` not followed by `$`. All green before changing code.
- [ ] Add `EmbeddedSegment` and `parseEmbedded(string): list<EmbeddedSegment>` per spec section 4.1. Each expression
      segment is validated by the same `Parser`; unterminated/invalid raises `ExpressionSyntaxException` with the
      segment's absolute offset.
- [ ] Add the new method to `ExpressionEngineInterface` and `ExpressionEngine`; update any other implementors (grep
      `implements ExpressionEngineInterface`, including test doubles and Laravel bindings).
- [ ] Rewrite `StringInterpolator::interpolate` to iterate segments. Constructor gains
      `ExpressionEngineInterface $expressions` (Task 0 tells how `StringInterpolator` is constructed in
      `EvaluationEngine`; update that wiring). Preserve null/scalar/JSON behavior exactly.
- [ ] Decide malformed-input behavior: today an unterminated `{$` passes through as literal text. Keep that for
      **interpolation** (runtime must not throw on odd step data) and let `parseEmbedded` throw for **validation**.
      Implement via a `lenient` flag or by catching in the interpolator; pin with a test either way.
- [ ] Switch document validator rules that have their own `{$...}` regex (if any) to `parseEmbedded`.
- [ ] Add the guard test: `StringInterpolator` source contains no `preg_replace_callback`.
- [ ] Add corpus entries in `embedded.json` and extend `ExpressionCorpusTest` for segment expectations.
- [ ] Commit: `feat(expression): grammar-based embedded expression splitting`.

**Done when:** all characterization tests still green, new boundary tests green, document suite green.

---

### Task 4: Bounded AST caches (G6)

**Files:**

- Create: `packages/expression/src/Support/AstCache.php`
- Modify: `packages/evaluation/src/ExpressionEvaluator.php`, `packages/evaluation/src/Condition/ConditionEvaluator.php`
- Test: `packages/expression/tests/Support/AstCacheTest.php`, `packages/evaluation/tests/ParseOnceTest.php`

- [ ] Implement `AstCache` (spec 4.5): `get`, `put`, bounded FIFO/LRU, default 256, `final`, no static state.
- [ ] `ExpressionEvaluator`: take `Parser` and `AstCache` by constructor (defaults preserve `new ExpressionEvaluator()`
      call sites; check all in Task 0's grep). Replace `new ExpressionParser()->parse(...)` with cache lookup then parse.
- [ ] `ConditionEvaluator`: cache the `ConditionNode` by condition string the same way. The Lexer/Parser pair is
      already held as instance fields; `Parser` has mutable `position`/`tokens` state, so **verify parsing is not
      re-entrant** and do not share one `Parser` across concurrent uses (PHP is single-threaded, but nested evaluation
      through plugins could re-enter).
- [ ] Cache successful parses only; a syntax error must throw every time.
- [ ] Test with a counting `Parser` double: evaluating the same expression N times parses once; N+1 distinct
      expressions with max=N evicts (bounded); a syntax error is not cached.
- [ ] Reflection test: `AstCache` has no static properties.
- [ ] Commit: `perf(evaluation): cache parsed expressions and conditions per engine`.

**Done when:** success criterion 4 holds and no behavior test changed.

---

### Task 5: RFC 6901 JSON Pointer hardening (G8)

**Files:**

- Modify: `packages/evaluation/src/JsonPointer.php`, `packages/evaluation/src/ExpressionEvaluator.php`
- Test: `packages/evaluation/tests/JsonPointerTest.php`

- [ ] Characterization tests of current behavior for: `''`, `/`, `/a`, `/a/b`, `/a~1b`, `/a~0b`, `//a`, `a/b`,
      list index `/items/0`, missing keys.
- [ ] Add the RFC 6901 expectation table (section 5 of the RFC): document and pointers from the RFC's own example
      (`""`, `/foo`, `/foo/0`, `/`, `/a~1b`, `/c%d`, `/e^f`, `/g|h`, `/i\j`, `/k"l`, `/ `, `/m~0n`) with expected values.
      Mark the currently-failing rows.
- [ ] Fix `resolve()`: strip exactly one leading `/` (`substr($pointer, 1)`), return `null` for a non-empty pointer
      not starting with `/`, keep `~1` before `~0` unescape order. Add `JsonPointer::isValid(string): bool`.
- [ ] Fix `ExpressionEvaluator` scalar+pointer (`InputRef`, `OutputPart`, and any `MessageRef` path): apply the pointer
      only to arrays; a scalar with a pointer returns `null`; `null` returns `null`.
- [ ] Decide `-` and percent-decoding: check Task 0's call-site list and the spec. If the spec's `#/...` forms are
      URI-fragment style, implement percent-decoding in the **parser projection**, not in `resolve()`; otherwise document
      "not supported" in the test table. Record the decision.
- [ ] Run `test-runner` and `test-document`: pointer call sites live there too.
- [ ] Commit: `fix(evaluation): resolve JSON pointers per RFC 6901`.

**Done when:** RFC table green; scalar+pointer returns null; nothing else in the monorepo regressed.

---

### Task 6: Comparison semantics audit and condition accessors (G4, G5)

**Files:**

- Modify (as decided): `packages/evaluation/src/Condition/{Lexer,Parser,ConditionEvaluator}.php`,
  `Condition/Ast/RuntimeExpr.php`; create `Condition/Ast/Accessor.php`
- Test: `packages/evaluation/tests/Condition/ComparisonSemanticsTest.php`, `.../ConditionAccessorTest.php`

- [ ] **Spec check (G5):** read the Criterion Object section. For each row of spec section 3/G5 record: what the
      spec says, what we do, verdict (`conformant` / `divergent` / `unspecified`). Put the table in the test file
      docblock.
- [ ] Pin every `conformant` and `unspecified` row with a named test. For `divergent` rows, write the failing
      test first, then fix `ConditionEvaluator`. Keep each fix a separate commit.
- [ ] **Spec check (ABNF):** check whether the spec defines an ABNF for simple conditions. If yes, vendor it as
      `packages/evaluation/resources/grammar/arazzo-simple-condition.abnf` (same provenance rule as Task 1), share the
      oracle with the evaluation tests (move it to a monorepo-shared test-support location if needed), and add a
      differential test for the condition `Parser`. If no, record that and author no normative grammar.
- [ ] **Spec check (G4):** find the clause on property dereference / index access in simple conditions (the
      `swaggerexpert/arazzo-criterion` README cites spec 1.1.0's Criterion Object section). If the clause exists:
      implement per spec 4.4 (Accessor list; condition Lexer splits base and accessors; evaluator navigates arrays
      and ArrayAccess-free structures, null-safe). If it does not exist: add a docblock noting the extension decision
      and **do not implement**.
- [ ] If implemented: tests for `$response.body.items[0].id == 1`, missing member -> null, index out of range ->
      null, accessors on a non-array -> null, and interplay with `&&`/`||`/`!`/parentheses.
- [ ] Extend the corpus with a `conditions` file (condition string, context, expected boolean) in the same
      language-agnostic style so other implementations can run it.
- [ ] Commit(s): `test(evaluation): pin simple-condition semantics`, then `fix:` / `feat:` per change.

**Done when:** every G5 row has a verdict and a test; G4 is either implemented per spec or explicitly declined.

---

### Task 7: Criterion diagnostics (G7)

**Files:**

- Create: `packages/evaluation/src/Data/CriterionResult.php`, `Enum/CriterionOutcome.php`,
  `Enum/CriterionReason.php`, `Interfaces/CriterionObserverInterface.php`
- Modify: `packages/evaluation/src/CriteriaEvaluator.php`, `Interfaces/CriteriaEvaluatorInterface.php`
  (additive `evaluateCriteriaDetailed`), `EvaluationEngine.php` / `EvaluationEngineInterface.php` (expose it)
- Test: `packages/evaluation/tests/CriteriaEvaluatorDetailedTest.php`

- [ ] Snapshot test: run the **entire existing criteria test suite** and record that it stays green through this task
      (it is the regression guard for "bool behavior unchanged").
- [ ] Add the enums and `CriterionResult` per spec 4.6.
- [ ] Refactor `CriteriaEvaluator::evaluateCriteria` so the per-criterion body returns a `CriterionResult` instead of a
      bool: `ConditionSyntaxException` -> `Error/SyntaxError`; unresolved `context` -> `Error/ContextUnresolved`;
      selector/regex/XPath `\Throwable` -> `Error/SelectorError`; plugin throw -> `Error/PluginError` (currently a
      plugin throw is **not** caught; decide: catch and report, or keep propagating. Pin whichever with a test and
      record it); a clean false -> `Failed/Evaluated`; true -> `Passed/Evaluated`.
- [ ] Add `evaluateCriteriaDetailed()` returning the ordered list (stops at first non-`Passed`, like today).
      `evaluateCriteria()` delegates: `true` iff the last result is `Passed` or the list is empty.
- [ ] Optional observer: add `CriterionObserverInterface` (nullable constructor arg); call it once per evaluated
      criterion. **Do not** wire it into runner telemetry in this plan.
- [ ] Tests per criterion type (simple, regex, jsonpath, xpath, plugin) for Passed, Failed and each Error reason.
- [ ] Update every implementor of `CriteriaEvaluatorInterface` (grep, including Laravel bindings and test doubles).
- [ ] Commit: `feat(evaluation): per-criterion outcome diagnostics`.

**Done when:** existing criteria tests unchanged and green; detailed API distinguishes Failed from Error.

---

### Task 8: Reference coverage (G9)

**Files:** `packages/evaluation/src/ExpressionEvaluator.php`, tests; possibly `docs/architecture/04-*.md`

- [ ] Enumerate from the spec: component types under `components` that runtime expressions may reference
      (`$components.inputs`, `parameters`, `successActions`, `failureActions`) and the `sourceDescriptions` sub-paths.
      Table: form, spec says, we resolve (yes/no).
- [ ] For each **resolvable-by-spec** form we return `null` for: implement, with a test.
- [ ] For each form that cannot be meaningfully resolved at runtime (e.g., action objects): pin `null` with a
      test and add a line to the architecture doc describing it as by-design.
- [ ] `MessageRef`: confirm with the runner protocol code whether message transports populate a message anywhere. If
      they do not, document that `$message.*` currently reads the HTTP response and mark a follow-up; do not change here.
- [ ] Commit: `feat(evaluation): resolve remaining spec-defined component and source references`.

**Done when:** table complete; no spec-defined, runtime-resolvable form returns `null` by accident.

---

### Task 9: Docs, arch tests, finish

**Files:** `docs/architecture/04-*.md` (expression evaluation), `packages/*/tests/ArchTest.php`, spec status line

- [ ] Update the expression-evaluation architecture doc: ABNF artifact and oracle, divergence register, embedded
      splitting, cache, error positions, diagnostics API,
      corpus location and format.
- [ ] Add the fitness tests from spec section 6 (no regex splitting in `StringInterpolator`, `AstCache` has no static
      state); confirm existing arch tests (expression must not use evaluation/document/runner) still pass.
- [ ] Run the full matrix: test + analyse for expression, evaluation, document, runner, cli, laravel. Pint on touched
      files.
- [ ] Walk the spec's Success Criteria 1-8; tick each with evidence (test name) in the PR description.
- [ ] Set the spec `Status` to `Implemented` (or `Partially implemented` listing declined items from Tasks 3/6/8).

**Done when:** all success criteria evidenced; spec status updated.

---

## Sequencing

`0 -> 1 -> 1b -> 2 -> 3 -> 4 -> 5 -> 6 -> 7 -> 8 -> 9`. Rationale: the vendored ABNF and corpus (1) and the oracle (1b)
are the safety net for every later parser
change; positioned errors (2) precede the splitter (3) because the splitter reuses them; pointer hardening (5) precedes
criteria diagnostics (7) so JSONPath/selector results are not re-baselined twice. Tasks 5, 6, 8 are independent of
each other and can run in parallel after 4.

## Follow-ups (explicitly out of scope)

- Wire `CriterionObserverInterface` into runner telemetry.
- Publish the corpus as a standalone fixture repo or contribute it to the conformance effort
  (OAI/Arazzo-Specification#448); coordinate format with the maintainers first.
- Reconsider a parser **generated** from the ABNF if the oracle and corpus keep exposing divergences.
- Offer the vendored ABNF, the corpus format and the divergence register to the Arazzo conformance effort.
