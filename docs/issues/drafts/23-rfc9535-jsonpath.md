# Draft issue 23 — RFC 9535 JSONPath conformance and query budgets

- **Suggested title:** `[Feature] Complete RFC 9535 JSONPath support and bound query cost`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #139 Expression parsing parity & hardening)
- **Packages:** `expression`, `evaluation`, `document`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/expression` `JsonPathEvaluator` and selector plugins; `packages/evaluation` selector execution; `packages/document` `SelectorTypeSupportedRule`.
- **Arazzo spec version:** 1.0.x and 1.1.0 selector objects (`type: jsonpath`, `version: rfc9535`).
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core.
- **Kind / level:** `kind:feature`, `level:story`, under #139 (Expression parsing parity & hardening).
- **Priority:** P3 — correctness and safety of selector evaluation; not blocking the runtime wiring.

### 📝 2. Problem Statement & Intent

`SelectorTypeSupportedRule` advertises `rfc9535` as the supported JSONPath version
(`packages/document/src/Validator/Rules/SelectorTypeSupportedRule.php:22-26`).
The evaluator behind it is a classic engine:

```php
$jsonPath = new JSONPath($data);
$result = $jsonPath->find($normalized);
```

(`packages/expression/src/JsonPathEvaluator.php:32-34`)

The only RFC 9535 accommodation is a regex translation of `[?expr]` to
`[?(expr)]` (`JsonPathEvaluator.php:54-63`). The engine does not implement:

- the RFC 9535 function extensions `length()`, `count()`, `match()`, `search()`,
  `value()`;
- RFC 9535 comparison and type rules in full;
- normalized path output;
- any admission budget, so a pathological query such as `$..*` on a large
  response can consume unbounded time and memory.

The validator therefore claims a conformance level the engine does not provide,
and evaluation has no cost ceiling.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Replace the classic evaluation path with an RFC 9535 conformant engine, or
  wrap the existing one behind a conformance adapter that implements the
  missing functions and comparison rules.
- Support function extensions in filter selectors: `length()`, `count()`,
  `match()`, `search()`, `value()`.
- Support the full RFC 9535 filter grammar, including logical operators,
  existence tests and nested filters.
- Return results with a well-defined collection shape for single and multiple
  matches, matching the current public contract where possible.
- Add query admission budgets:
  - maximum expression length,
  - maximum selector depth,
  - maximum nodes visited during evaluation,
  - maximum result set size.
- Exceeded budgets produce a stable `expression.jsonpath_budget` error, not a
  hang.
- Align `SelectorTypeSupportedRule` with reality: if a feature is not supported,
  do not advertise `rfc9535`; if it is, pin conformance with tests.
- Add the RFC 9535 test suite or a representative subset as fixtures.

**Non-goals**

- Changing the Arazzo expression grammar (that is #142).
- JSON Pointer evaluation (separate selector type, already supported).
- XPath versions.
- A new public JSONPath API; keep `ExpressionEngine::jsonPath()`.

**Prior art in this repository**

- `JsonPathEvaluator` already isolates evaluation behind one static method, so
  the engine can be replaced without touching callers.
- `SelectorTypeSupportedRule` already lists versions and can be used as the
  conformance declaration.
- `normalizeFilters()` establishes a translation seam that can be removed once
  the engine understands RFC 9535 directly.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `length()`, `count()`, `match()`, `search()` and `value()` work in filter selectors.
- [ ] Existence tests, logical operators and nested filters follow RFC 9535.
- [ ] Single-match and multi-match result shapes are documented and tested.
- [ ] Query length, depth, node-visit and result-size budgets are enforced.
- [ ] A budget violation produces `expression.jsonpath_budget` with the limit name.
- [ ] `SelectorTypeSupportedRule` and the evaluator agree: no advertised version is partially implemented.
- [ ] RFC 9535 fixtures cover the function extensions and the filter grammar.
- [ ] Existing selectors in the OAI corpus still evaluate to the same results.
- [ ] PHPStan max and Pint pass.

### 🧬 7. Source Grounding & Code Location

- `packages/expression/src/JsonPathEvaluator.php:18-63` — classic engine and filter translation.
- `packages/document/src/Validator/Rules/SelectorTypeSupportedRule.php:22-26` — advertises `rfc9535`.
- `packages/expression/src/Plugins/JsonPathSelectorPlugin.php` — selector entry point.
- `packages/evaluation/src/EvaluationEngine.php:134` — public `jsonPath()` implementation.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/expression/src/JsonPathEvaluator.php#L18-L63"><code>JsonPathEvaluator.php:18-63</code></a> @ <code>ea8c55a</code></summary>

```php
public static function evaluate(string $expression, array|object $data): mixed
{
    $normalized = self::normalizeFilters($expression);
    // ...
    $jsonPath = new JSONPath($data);

    $result = $jsonPath->find($normalized);

    $arrayResult = $result->getData();
    // ...
}

public static function normalizeFilters(string $expression): string
{
    // `[?BODY]` -> `[?(BODY)]`
    return (string) preg_replace('/\[\?([^\]]*)\]/', '[?($1)]', $expression);
}
```

</details>

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** evaluation results may change for queries that relied on classic-engine quirks; document as a minor-version behaviour change.
- **Blast radius:** `packages/expression`, `packages/evaluation`, `packages/document` validation claims.
- **Severity:** medium — correctness and denial-of-service resistance.
- **Rollback:** keep the classic engine behind a feature flag while the conformant engine is validated against the corpus.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Evaluation delegates to a classic engine | T1 | `JsonPathEvaluator.php:32-34` | direct read |
| The only RFC 9535 accommodation is filter spelling | T1 | `JsonPathEvaluator.php:54-63` | direct read |
| The installed engine has no function extensions | T1 | `packages/core/vendor/softcreatr/jsonpath/src` has no `length`/`match`/`search` implementations | direct read |
| `rfc9535` is advertised as supported | T1 | `SelectorTypeSupportedRule.php:22-26` | direct read |
| No query budget exists | T1 | `JsonPathEvaluator.php` has no limits | direct read |
| `#142` covers the expression grammar, not JSONPath | T1 | issue #142 title and body | read |
