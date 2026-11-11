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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] A new rule (suggested code: `parameter.reusable_ref_resolves`) validates every `Reusable` entry in workflow parameters, step parameters, workflow success/failure action parameters, and step `onSuccess`/`onFailure` action parameters.
- [ ] The reference must start with `$components.parameters.` and name an existing key in `components.parameters`; otherwise an error is reported at the entry's `reference` pointer.
- [ ] Reusable references that resolve are accepted.
- [ ] The rule emits an error, not a warning, and works in both strict and non-strict modes (it is a structural MUST).
- [ ] Pest tests cover: missing component; wrong component namespace (e.g. `$components.inputs.x`); resolving component; workflow-level and step-level lists; action parameter lists.
- [ ] The runtime `RuntimeException` remains as a defence-in-depth backstop but is no longer the first signal.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** none; invalid documents start failing validation.
- **Blast radius:** `packages/document`; execution becomes more predictable because broken references are caught before side effects.
- **Severity:** medium-high — mid-workflow `RuntimeException` after network calls is costly.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Missing reusable parameter references validate | T1 | Executed validator run on the artifact | zero errors |
| Only action reusable refs are validated | T1 | `ActionReusableRefResolvesRule` and rule list | direct read |
| The engine throws only at execution time | T1 | `ReusableParameterResolver.php:25-53` | direct read |
| The spec requires the reference to resolve | T2 | Arazzo 1.1.0 Reusable Object clauses | spec text |
