# Draft issue 28 — Reject duplicate `requestBody.replacements[].target` values

- **Suggested title:** `[Bug] Duplicate payload replacement targets are not rejected`
- **Kind:** `kind:bugfix`
- **Level:** `level:story` (suggested parent: #118 Document & runner validation hardening)
- **Packages:** `document`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/document` validator rules; `StepRequestBodyReplacementsTargetRule` (exists, checks target resolvability only); JSON Schema `arazzo-1.1.schema.json` `replacements.uniqueItems` (structural only).
- **Arazzo spec version:** 1.0.x and 1.1.x (Payload Replacement Object §5.8.15 / §4.6.14).
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core.
- **Kind / level:** `kind:bugfix`, `level:story`, under #118 (Document & runner validation hardening).
- **Priority:** P2 — two replacements targeting the same location cause silent content override; the second write wins with no diagnostic.

### 📝 2. Problem Statement & Intent

A `requestBody.replacements` list maps target locations (JSON Pointer / JSONPath / XPath) to values. The spec says the list describes "a list of locations and values to set within a payload"; two entries with the same `target` are ambiguous and the second silently overrides the first.

php-arazzo does not check target uniqueness:

- `StepRequestBodyReplacementsTargetRule` validates that each `target` resolves against the request body shape but does not compare targets across entries.
- JSON Schema `uniqueItems: true` on `replacements` only rejects structurally identical objects, not semantically equal `target` strings with different `value`s.
- Probe confirmed: a document with two replacements both targeting `/a` (different values) validates `valid=true` with no error.

Expected: a validator rule (or extension of `StepRequestBodyReplacementsTargetRule`) reports an error when two replacements in the same `requestBody` share the same `target`, naming the duplicate target and its locations.

### 📦 3. System Dependency Matrix

- **Upstream:** Arazzo spec §5.8.14 Request Body Object / §5.8.15 Payload Replacement Object; `requestBody` is parsed by `Parser::parseRequestBody()`; `Replacement` DTO holds `target`/`value`/`targetSelectorType`.
- **Downstream:** `StepExecutor`/`RequestCompiler` apply replacements at execution time; a duplicate target causes the second replacement to win with no warning.
- **Internal seams used:** `StepRequestBodyReplacementsTargetRule` already walks `$s->io->requestBody->replacements`; the check can be added there or as a new rule.
- **Packages touched:** `packages/document` (primary).
- **Scope boundary:** per-step `requestBody.replacements` only. Workflow-level parameters and action parameters have their own uniqueness rules (draft 04 covers duplicate parameters/actions; StepOutputsUnique/ComponentsUniqueNames are draft 03).

### 📄 4. Structural Artifacts & Raw Inputs

Minimal document that reproduces the defect:

```yaml
arazzo: 1.1.0
info: { title: Repro, version: "1" }
sourceDescriptions:
  - name: api, url: https://example.com/openapi.yaml, type: openapi
workflows:
  - workflowId: wf
    steps:
      - stepId: one
        operationId: $sourceDescriptions.api.createThing
        requestBody:
          contentType: application/json
          payload:
            a: 1
          replacements:
            - target: /a
              value: "2"
            - target: /a
              value: "3"
```

Both replacements target `/a`; validation reports `valid=true`. At runtime, `/a` ends up `3` (or `2`, order-dependent) with no diagnostic that the author likely made a mistake.

Expected: validation fails with an error such as "Duplicate replacement target '/a' in requestBody of step 'one'" pointing at both entries.

### 🌀 5. Runtime Execution State Context

- Step: `one` with `requestBody.replacements` containing duplicate `target`.
- Validation phase: `Validator::validate()` → `RuleSet::activeRules()` → `StepRequestBodyReplacementsTargetRule::check()` (no uniqueness check).
- Execution phase (not reached because no error): `RequestCompiler` applies replacements sequentially; the last write to `/a` wins.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Reject duplicate `requestBody.replacements[].target` values at validation time, preventing silent content override where the second replacement silently wins over the first.

**Architectural Boundary:** Bounded context = document validation. Package: `packages/document` (validator rules). Per-step `requestBody.replacements` only; workflow-level parameters/actions have their own uniqueness rules (draft 04).

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Duplicate detection via hash set is O(replacements) per request body; validation-only, no runtime impact.
- [ ] **Flexible:** Rule additive to `RuleSet::default()`; configurable via draft 27 policy; can extend existing `StepRequestBodyReplacementsTargetRule` or add a new `RequestBodyReplacementsUniqueRule`.
- [ ] **Maintainable:** Single-responsibility rule; error names duplicate target and indices/paths of both entries; logged via `ErrorCollector`.
- [ ] **Operable:** `arazzo validate` emits deterministic `step.requestBody_replacements_duplicate_target` error; rollback by removing the rule (schema `uniqueItems` remains structural only).
- [ ] **Reliable:** Deterministic validation; idempotent; prevents silent data-integrity issues at authoring time.
- [ ] **Safe:** Read-only validation; prevents wrong payload content from being sent at runtime.
- [ ] **Secure:** Input validation strengthens conformance; no authorization impact.
- [ ] **Suitable:** Implements spec intent that replacements describe "a list of locations and values to set within a payload" — duplicate targets are ambiguous.
- [ ] **Usable:** Error names the duplicate target and both entry locations; author can quickly identify the mistake.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: Duplicate replacement targets rejected**
- **Given** an Arazzo document (`doc.arazzo`) containing workflow index 0, step index 0 with `requestBody: { contentType: "application/json", payload: { "a": 1 }, replacements: [{ target: "/a", value: "2" }, { target: "/a", value: "3" }] }`; both replacements target the same location `/a` with different values; `RequestBodyReplacementsUniqueRule` (or extended `StepRequestBodyReplacementsTargetRule`) is registered in `RuleSet::default()` with code `step.requestBody_replacements_duplicate_target`.
- **When** `php bin/console arazzo validate doc.arazzo` invokes `Validator::validate()` against the parsed `ArazzoDocument`.
- **Then** validation fails with error code `step.requestBody_replacements_duplicate_target`; `ErrorCollector` records the error under `errors`; the error names the duplicate target `/a` and the indices/paths of both entries (e.g., `/workflows/0/steps/0/requestBody/replacements/0/target` and `/workflows/0/steps/0/requestBody/replacements/1/target`); `ValidationResult::isValid()` returns `false`.

**Scenario 2: Same target with different `targetSelectorType` still flagged**
- **Given** two replacements targeting `/a` but one uses JSON Pointer (`targetSelectorType: "jsonpointer"`) and one uses JSONPath (`targetSelectorType: "jsonpath"`); target identity is the string `/a`, not the type.
- **When** `Validator::validate()` applies the uniqueness rule.
- **Then** both replacements are flagged as duplicates; the error names target `/a` with indices of both entries; the spec does not say type differentiates targets; target identity is the target string itself.

**Scenario 3: Distinct targets pass unchanged**
- **Given** replacements targeting `/a` and `/b` (distinct targets).
- **When** `Validator::validate()` applies the uniqueness rule.
- **Then** validation passes; `ValidationResult::isValid()` returns `true`; `ErrorCollector` records zero errors; no false positives for distinct targets.

**Scenario 4: Error code and pointer**
- **Given** duplicate targets in step 0 of workflow 0; `ValidatorTest` fixture-tests this case.
- **When** validation fails.
- **Then** error code is `step.requestBody_replacements_duplicate_target`; error pointer is `/workflows/0/steps/0/requestBody/replacements/1/target` (pointing at the second duplicate entry); the error message identifies both entries and the duplicate target value.

**Scenario 4: Error code and pointer**
- **Given** duplicate targets in step 0 of workflow 0
- **When** validation fails
- **Then** error code `step.requestBody_replacements_duplicate_target` with pointer `/workflows/0/steps/0/requestBody/replacements/1/target`

---

## 9. Actionable Implementation & Verification Checklist

- [ ] Validator rule reports error when two replacements in same `requestBody` share same `target` string
- [ ] Error names duplicate `target` and indices/paths of both entries (e.g. `/workflows/0/steps/0/requestBody/replacements/1/target`)
- [ ] Same `target` with different `targetSelectorType` still flagged
- [ ] Distinct targets pass unchanged
- [ ] `ValidatorTest` case: duplicate targets → invalid with error code
- [ ] `ValidatorTest` case: distinct targets → valid
- [ ] `StepRequestBodyReplacementsTargetRule` (or new `RequestBodyReplacementsUniqueRule`) registered in `RuleSet::default()`
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Error logging via `ErrorCollector` instrumented

### 🧬 10. Source Grounding & Code Location

- `packages/document/src/Validator/Rules/StepRequestBodyReplacementsTargetRule.php` — existing rule; checks target resolvability, not uniqueness.
- `packages/document/resources/schemas/arazzo-1.1.schema.json:383` — `replacements.uniqueItems: true` (structural dedup only).
- `packages/document/src/Validator/RuleSet.php:143-157` — rule registration list.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/document/src/Validator/Rules/StepRequestBodyReplacementsTargetRule.php:16-45"><code>StepRequestBodyReplacementsTargetRule.php:16-45</code></a> @ <code>ea8c55a</code></summary>

```php
final class StepRequestBodyReplacementsTargetRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                $body = $s->io->requestBody;
                if ($body === null || $body->replacements === []) {
                    continue;
                }
                foreach ($body->replacements as $k => $r) {
                    // checks $r->target resolves against the body shape; no uniqueness check
                    ...
                }
            }
        }
    }
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** none (validator is `@internal`); new validation error may cause previously-valid documents to fail `arazzo validate`.
- **Blast radius:** documents with duplicate replacement targets (authoring error); no runtime behaviour change for valid documents.
- **Severity:** medium — silent content override is a data-integrity risk; caught at validation time.
- **Rollback:** remove the rule; `uniqueItems` schema check remains (structural only).

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No uniqueness check on replacement targets | T1 | `StepRequestBodyReplacementsTargetRule` reads; probe-repl.php → `valid=true` for duplicate `/a` | executed probe |
| JSON Schema `uniqueItems` is structural only | T1 | `arazzo-1.1.schema.json:386`; probe confirms distinct-value duplicates pass | executed probe |
| Spec describes replacements as "a list of locations and values to set within a payload" | T2 | Arazzo spec §5.8.14 Request Body Object | external spec |
| Rule registration point is `RuleSet::default()` | T1 | `RuleSet.php:143-157` | direct read |
