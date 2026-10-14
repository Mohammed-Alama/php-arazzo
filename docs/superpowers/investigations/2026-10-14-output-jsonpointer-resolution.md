# Investigation — which seam resolves the `#/…` suffix on a step output reference?

- **Date:** 2026-10-14
- **Focus:** `architecture` · **Depth:** `standard`
- **Owner:** `arazzo-detective` (first live run of the skill)
- **Question:** When an Arazzo expression such as
  `{$steps.fetch.outputs.user#/profile/email}` carries a `#/…` suffix, which
  evaluator resolves the suffix — the JSONPath seam or the JSON Pointer seam —
  and where does that happen?

## Epistemic Tier Summary

| Claim | Tier | Anchor |
| --- | --- | --- |
| The `#/…` suffix is parsed into an **OutputPart JSON Pointer**, not a JSONPath | **1** | `packages/expression/src/Parser.php:135,244-267` + passing test `ParserTest.php:125` |
| Resolution runs through `JsonPointer::resolve`, never `JsonPathEvaluator` | **1** | `packages/expression/src/ExpressionEvaluator.php:105-111` + passing test `ExpressionEvaluatorTest.php:179` |
| The JSONPath seam serves `selector` objects and raw `$…` JSONPath only | **1** | `SelectorEvaluator.php:39-45`, `JsonPathExpressionPlugin.php:27-48` |
| A real runner run wires these the same way end to end | **3** | inferred from the unit seams; the runtime composition was not traced |

## 1. Ontological Map

Entities that actually exist, read directly:

- `Alama\Arazzo\Expression\Parser::parseStepRef()`
  (`packages/expression/src/Parser.php:119-143`) — parses
  `steps . <id> . outputs . <name> [ # /… ]`. The `outputs` branch is
  `parseNamedPart(..., OutputPart::class, allowPointer: true)` (line 135).
- `Parser::parseJsonPointer()` (lines 244-267) — after a `Hash` token, consumes
  `/segment` pairs into a `string $pointer`.
- `Alama\Arazzo\Expression\Ast\OutputPart`
  (`packages/expression/src/Ast/OutputPart.php`) — carries `string $name` and
  `?string $jsonPointer`.
- `Alama\Arazzo\Expression\ExpressionEvaluator::evaluateAst()`
  (`packages/expression/src/ExpressionEvaluator.php:105-111`) — the `OutputPart`
  branch: reads `stepData['outputs'][$name]`, then, when a pointer is present,
  `$this->jsonPointer->resolve(...)` (line 109).
- `Alama\Arazzo\Expression\JsonPointer::resolve()`
  (`packages/expression/src/JsonPointer.php:16-35`) — an RFC 6901 walker
  (splits on `/`, unescapes `~1`→`/`, `~0`→`~`).
- The **other** seam: `Alama\Arazzo\Expression\JsonPathEvaluator::evaluate()`
  (`packages/expression/src/JsonPathEvaluator.php:18-46`) — wraps
  `Flow\JSONPath\JSONPath`, plus RFC 9535 filter normalisation.
  Reached from `SelectorEvaluator::evaluate()`
  (`packages/expression/src/SelectorEvaluator.php:39-45`, `ExpressionType::JsonPath`)
  and from `Alama\Arazzo\Evaluation\Plugins\JsonPathExpressionPlugin::evaluate()`
  (`packages/evaluation/src/Plugins/JsonPathExpressionPlugin.php:53-56`).
- `JsonPathExpressionPlugin::supports()` (lines 27-48) explicitly **refuses**
  raw `$`-strings matching `$inputs|$steps|$response|$request|$workflows.`
  (line 43) and `${…}` (line 37) — so Arazzo step references never reach the
  JSONPath seam.

## 2. Source Reliability Map

| Fact | Rank | Source |
| --- | --- | --- |
| `outputs` branch passes `allowPointer: true`; `#` becomes `jsonPointer` | 1 | direct read, `Parser.php:135,171-177` |
| `OutputPart` resolution calls `JsonPointer::resolve`, not `JsonPathEvaluator` | 1 | direct read, `ExpressionEvaluator.php:105-111` |
| The behaviour is asserted and green | 1 | `pest … --filter "json pointer suffixes"` → 1 passed; `--filter "parses"` → 15 passed |
| The JSONPath seam is a separate concern | 1 | direct read, `SelectorEvaluator.php:39-45` + `JsonPathExpressionPlugin.php:43` |
| End-to-end runtime composition is the same | 3 | inference from the unit seams (not traced through the engine) |

No fact in the top four rows is stated above rank 1, and the fifth is flagged.

## 3. Justification Chain

- **Claim.** `{$steps.fetch.outputs.user#/profile/email}` resolves via JSON
  Pointer, and the JSONPath seam never sees `#/…`.
- **Prior** (before reading): *medium.* The ancestor names everything
  "JsonPath"; and Arazzo's own `selector` objects are JSONPath, so it is
  plausible the suffix is JSONPath too.
- **Evidence.** `parseStepRef` routes the `outputs` sub-part to
  `parseNamedPart(..., allowPointer: true)`; only a `Hash` token triggers
  pointer parsing (line 171-177). The evaluator's `OutputPart` branch calls
  `$this->jsonPointer->resolve(...)` (line 109) and never touches
  `JsonPathEvaluator`. The plugin's `supports()` rejects `$steps.…` (line 43).
  The behaviour is locked by two green tests.
- **Posterior.** *very high (≈0.98).* The static read, the `supports()`
  exclusion, and two passing tests agree three ways.

## 4. Failure Modes Detected

- **Plausible-but-unfounded (naming trap).** The instinct to call the `#/…`
  suffix "JSONPath" because the sibling seam is JSONPath. Falsified by the
  `Hash`/`parseJsonPointer` path and by `supports()`'s explicit rejection.
  *(This is the mode the skill exists to catch; it fired on me mid-read.)*
- **Two-seams-confused.** A bug report phrased as "JSONPath fails on `#/id`" is
  misframed and will send the reader to `JsonPathEvaluator`, which is never
  invoked on this code path.
- **Unfalsifiable framing.** "It probably uses JSONPath" — the falsifier below
  was required before the conclusion was allowed to stand.

**Falsifier searched for and refuted.** The claim would be false if (a) the
`OutputPart` branch called `JsonPathEvaluator`, or (b) `JsonPathExpressionPlugin`
claimed `$steps.…`. (a) is refuted at `ExpressionEvaluator.php:109`; (b) at
`JsonPathExpressionPlugin.php:43`.

## 5. Confidence Summary

| Finding | Confidence |
| --- | --- |
| `#/…` on an output is an RFC 6901 JSON Pointer resolved by `JsonPointer` | 0.98 |
| `JsonPathEvaluator` is not on this path | 0.97 |
| Runtime composition is unchanged from the unit seams | 0.7 |

## 6. Recommendations

1. **Name the seam in future reports.** Say "JSON Pointer (`JsonPointer`)" or
   "JSONPath (`JsonPathEvaluator`)"; "path" alone is ambiguous in this codebase.
2. Keep this distinction in `references/grounding-operations.md` so the next
   investigator starts from it rather than rediscovering it.
3. If a future symptom is "output resolves null", check the three failure points
   in order: the pointer parse (`Parser.php:244-267`), the member read
   (`ExpressionEvaluator.php:106`), and the pointer walk
   (`JsonPointer.php:27-31`).

## 7. Epistemic Gaps

- The runtime composition of the evaluator (which engine wires
  `ExpressionEvaluator` into a live run) was **not** traced; Confidence 0.7 on
  that row. Closing it needs a read of the runner's engine factory, not the
  unit layer.
- No end-to-end run against `LoginAndRetrievePets.arazzo.yaml` was performed;
  the two unit anchors were taken as sufficient for a `standard`-depth question.
