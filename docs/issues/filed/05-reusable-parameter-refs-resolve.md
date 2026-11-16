# Draft issue 05 — Validate reusable parameter references

- **Suggested title:** `[Bug] Reusable parameter references are not validated; missing components fail only at runtime`
- **Kind:** `kind:bugfix`
- **Level:** `level:story` (suggested parent: #118 Document & runner validation hardening)
- **Packages:** `document`, `engine`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/document` validator, `packages/engine` reusable-parameter resolution.
- **Arazzo spec version:** 1.0.x and 1.1.x.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core.
- **Kind / level:** `kind:bugfix`, `level:story`.
- **Priority:** medium-high — a document validates green and then fails mid-run.

### 📝 2. Problem Statement & Intent

The spec says that when a Reusable Object is provided in a parameter list, it **MUST** link to a parameter defined in `components/parameters` of the current Arazzo document (§5.8.2, §5.8.5.1, §5.8.5.2, §5.8.5.3).

php-arazzo validates reusable references for **actions** (`ActionReusableRefResolvesRule`) but not for **parameters**. `StepParametersHaveNameRule` explicitly skips `Reusable` entries. As a result, `reference: $components.parameters.missing` validates successfully in workflow and step parameter lists, and in action parameter lists.

The failure appears only when the step executes: `ReusableParameterResolver::resolve()` throws a plain `RuntimeException`. This is too late, has no document pointer, and cannot be caught by `arazzo validate`.

### 📦 3. System Dependency Matrix

- **Upstream:** `packages/contracts` (`Reusable`, `Components::$parameters`, `SymbolTable`).
- **Parser:** `Parser::parseParameter()` returns `Parameter|Reusable`.
- **Validator:** new rule (or extension of `ActionReusableRefResolvesRule`) in `packages/document`.
- **Engine:** `ReusableParameterResolver` consumes the reference at execution-prep time.
- **Related:** #171 covers beyond-spec `in` values, not reusable resolution.

### 📄 4. Structural Artifacts & Raw Inputs

```yaml
arazzo: 1.1.0
info: { title: T, version: "1.0.0" }
sourceDescriptions:
  - name: api
    url: https://example.com/openapi.yaml
    type: openapi
workflows:
  - workflowId: wf
    parameters:
      - reference: $components.parameters.missing
    steps:
      - stepId: one
        operationId: $sourceDescriptions.api.getThing
        parameters:
          - reference: $components.parameters.missing
```

### 🌀 5. Runtime Execution State Context

- Validation only for the reproduced failure.
- Observed: **zero errors** from `Validator(RuleSet::default(...))`.
- Execution path: `ReusableParameterResolver::resolve()` calls `getParamterComponent()`, receives `null`, and throws `RuntimeException("Unresolvable reusable parameter reference ...")` when the workflow or step runs.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Validate that reusable parameter references resolve to existing components defined in `components/parameters`, so missing references fail at validation time rather than mid-execution with an obscure `RuntimeException`.

**Architectural Boundary:** Bounded context = document validation + engine reusable-parameter resolution. Packages: `packages/document` (new validator rule), `packages/engine` (`ReusableParameterResolver` as defence-in-depth backstop). Parser returns `Parameter|Reusable`; rule must cover workflow parameters, step parameters, and action parameter lists.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Validation-only; component existence lookup is O(1) via symbol table; no runtime execution impact.
- [ ] **Flexible:** Rule additive to `RuleSet::default()`; configurable via draft 27 policy; reuses existing `ActionReusableRefResolvesRule` prefix/existence logic.
- [ ] **Maintainable:** Single-responsibility rule; error pointer at the `reference` field; logged via `ErrorCollector`.
- [ ] **Operable:** `arazzo validate` emits deterministic `parameter.reusable_ref_resolves` errors; no deployment changes.
- [ ] **Reliable:** Deterministic validation; idempotent; no side effects; prevents mid-run `RuntimeException`.
- [ ] **Safe:** Read-only validation; prevents broken references from reaching execution.
- [ ] **Secure:** Input validation strengthens conformance; no authorization impact.
- [ ] **Suitable:** Implements Arazzo 1.1.0 §5.8.2, §5.8.5.1, §5.8.5.2, §5.8.5.3 (Reusable Object MUST link to a defined `components/parameters` entry).
- [ ] **Usable:** Precise pointer to the unresolvable `reference` entry aids author debugging.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: Missing reusable parameter reference in workflow parameters is rejected**
- **Given** an Arazzo 1.1.0 document (`doc.arazzo`) containing workflow index 0 with `parameters: [{ reference: "$components.parameters.missing" }]`; `components.parameters` does not define key `missing`; `ParameterReusableRefResolvesRule` (code `parameter.reusable_ref_resolves`) is registered in `RuleSet::default()` and active in both strict and non-strict modes.
- **When** `php bin/console arazzo validate doc.arazzo` invokes `Validator::validate()` against the parsed `ArazzoDocument`.
- **Then** validation fails with error code `parameter.reusable_ref_resolves` at pointer `/workflows/0/parameters/0/reference`; `ErrorCollector` records the error under `errors` (not `warnings`); the error message states `Reusable parameter reference '$components.parameters.missing' does not resolve to a defined component in components/parameters`; `ValidationResult::isValid()` returns `false`.

**Scenario 2: Missing reusable parameter reference in step parameters is rejected**
- **Given** an Arazzo 1.1.0 document containing workflow index 0, step index 0 with `parameters: [{ reference: "$components.parameters.missing" }]`; `components.parameters` does not define key `missing`.
- **When** `Validator::validate()` applies `ParameterReusableRefResolvesRule` to `Step::$parameters`.
- **Then** validation fails with error code `parameter.reusable_ref_resolves` at pointer `/workflows/0/steps/0/parameters/0/reference`; `ValidationResult::isValid()` returns `false`.

**Scenario 3: Wrong component namespace is rejected**
- **Given** an Arazzo 1.1.0 document containing workflow index 0 with `parameters: [{ reference: "$components.inputs.x" }]`; the reference starts with `$components.inputs.` not `$components.parameters.`.
- **When** `Validator::validate()` applies `ParameterReusableRefResolvesRule` to the workflow parameter list.
- **Then** validation fails with error code `parameter.reusable_ref_resolves` at pointer `/workflows/0/parameters/0/reference`; the error message states the reference must start with `$components.parameters.`; `ValidationResult::isValid()` returns `false`.

**Scenario 4: Resolving reusable reference is accepted**
- **Given** an Arazzo 1.1.0 document containing workflow index 0 with `parameters: [{ reference: "$components.parameters.x" }]`; `components.parameters` defines key `x` with a valid parameter definition.
- **When** `Validator::validate()` applies `ParameterReusableRefResolvesRule`.
- **Then** validation passes; `ValidationResult::isValid()` returns `true`; `ErrorCollector` records zero errors; the reusable reference that resolves is accepted.

**Scenario 5: Rule covers action parameter lists too**
- **Given** an Arazzo 1.1.0 document containing workflow index 0, step index 0 with `onSuccess: [{ name: "go", type: "goto", workflowId: "child", parameters: [{ name: "x", reference: "$components.parameters.missing" }] }]`; `components.parameters` does not define key `missing`.
- **When** `Validator::validate()` applies `ParameterReusableRefResolvesRule` to step-level `onSuccess` action parameter lists.
- **Then** validation fails with error code `parameter.reusable_ref_resolves` at pointer `/workflows/0/steps/0/onSuccess/0/parameters/0/reference`; `ValidationResult::isValid()` returns `false`.

**Scenario 6: Rule covers workflow-level action parameter lists**
- **Given** an Arazzo 1.1.0 document containing workflow index 0 with `successActions: [{ name: "go", type: "goto", workflowId: "child", parameters: [{ name: "x", reference: "$components.parameters.missing" }] }]`; `components.parameters` does not define key `missing`.
- **When** `Validator::validate()` applies `ParameterReusableRefResolvesRule` to workflow-level `successActions` parameter lists.
- **Then** validation fails with error code `parameter.reusable_ref_resolves` at pointer `/workflows/0/successActions/0/parameters/0/reference`; `ValidationResult::isValid()` returns `false`.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] New rule `ParameterReusableRefResolvesRule` (suggested code: `parameter.reusable_ref_resolves`) validates every `Reusable` entry in workflow parameters, step parameters, workflow success/failure action parameters, and step `onSuccess`/`onFailure` action parameters
- [ ] Reference must start with `$components.parameters.` and name an existing key in `components.parameters`; otherwise error at the entry's `reference` pointer
- [ ] Reusable references that resolve are accepted
- [ ] Rule emits an error (not a warning) in both strict and non-strict modes (structural MUST)
- [ ] Rule registered in `RuleSet::default()`
- [ ] Runtime `RuntimeException` in `ReusableParameterResolver` remains as defence-in-depth backstop but is no longer the first signal
- [ ] Pest tests: missing component; wrong component namespace (`$components.inputs.x`); resolving component; workflow-level and step-level lists; action parameter lists
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Error logging via `ErrorCollector` instrumented


### 🧬 10. Source Grounding & Code Location

- `packages/document/src/Validator/Rules/ActionReusableRefResolvesRule.php:18-42` — the existing action-only implementation and the prefix/existence logic to reuse.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/document/src/Validator/Rules/ActionReusableRefResolvesRule.php#L18-L42"><code>ActionReusableRefResolvesRule.php:18-42</code></a> @ <code>ea8c55a</code></summary>

```php
public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
{
    foreach ($doc->workflows as $wi => $w) {
        foreach ($w->steps as $si => $s) {
            $this->checkList($s->flow->onSuccess, 'successActions', $symbols, $errors, "/workflows/{$wi}/steps/{$si}/onSuccess");
            $this->checkList($s->flow->onFailure, 'failureActions', $symbols, $errors, "/workflows/{$wi}/steps/{$si}/onFailure");
        }
    }
}

/** @param list<mixed> $items */
private function checkList(array $items, string $componentType, SymbolTable $symbols, ErrorCollector $errors, string $base): void
{
    foreach ($items as $i => $item) {
        if (!$item instanceof Reusable) {
            continue;
        }
        // ... prefix and existence checks ...
    }
}
```

</details>

- `packages/engine/src/Execution/ReusableParameterResolver.php:25-53` — the runtime throw path.
- `packages/document/src/Parser/Parser.php:441-468` — reusable parameters are returned early and never validated.
- Spec: Arazzo 1.1.0 §5.8.2, §5.8.5.1, §5.8.5.2, §5.8.5.3 (Reusable Object clauses).

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** none; invalid documents start failing validation.
- **Blast radius:** `packages/document`; execution becomes more predictable because broken references are caught before side effects.
- **Severity:** medium-high — mid-workflow `RuntimeException` after network calls is costly.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Missing reusable parameter references validate | T1 | Executed validator run on the artifact | zero errors |
| Only action reusable refs are validated | T1 | `ActionReusableRefResolvesRule` and rule list | direct read |
| The engine throws only at execution time | T1 | `ReusableParameterResolver.php:25-53` | direct read |
| The spec requires the reference to resolve | T2 | Arazzo 1.1.0 Reusable Object clauses | spec text |
