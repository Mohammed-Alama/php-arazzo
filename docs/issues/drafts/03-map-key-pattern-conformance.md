# Draft issue 03 — Enforce map key patterns for outputs and components

- **Suggested title:** `[Bug] Output and components map keys are not validated against ^[a-zA-Z0-9.\-_]+$`
- **Kind:** `kind:bugfix`
- **Level:** `level:story` (suggested parent: #118 Document & runner validation hardening)
- **Packages:** `document`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/document` — validator rules and parser output maps.
- **Arazzo spec version:** 1.0.x and 1.1.x.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core.
- **Kind / level:** `kind:bugfix`, `level:story`.
- **Priority:** high — spec MUST, and two registered rules currently do nothing.

### 📝 2. Problem Statement & Intent

The spec requires map keys to match `^[a-zA-Z0-9\.\-_]+$` in three places:

- Workflow Object `outputs` (Arazzo 1.1.0 §5.8.2).
- Step Object `outputs` (§5.8.5.1).
- Every map under `components` (`inputs`, `parameters`, `successActions`, `failureActions`) (§5.8.6).

php-arazzo does not enforce any of them:

- `StepOutputsUniqueRule` is an empty `check()` method.
- `ComponentsUniqueNamesRule` is an empty `check()` method.
- `Parser::parseOutputsMap()` preserves arbitrary keys.

The official-schema rule does not catch these either. The vendored schema expresses output keys with `patternProperties` and components keys with `propertyNames`, but the `justinrainbow/json-schema` engine used by `OfficialSchemaRule` does not enforce those applicators in this configuration. Executed validation of `outputs: {"bad key!": ...}` and `components.parameters: {"bad key!": ...}` produces **zero key errors**.

The practical effect is that an invalid output name can be referenced by a runtime expression and fail only at execution time, and invalid component names can be emitted by code generators without any validation signal.

### 📦 3. System Dependency Matrix

- **Upstream:** `packages/contracts` (`Workflow::$outputs`, `StepIo::$outputs`, `Components`).
- **Parser:** `Parser.php` — `parseOutputsMap()`, `parseWorkflow()`, `parseStepIo()`, `parseComponents()`.
- **Validator:** `StepOutputsUniqueRule`, `ComponentsUniqueNamesRule`, `RuleSet`.
- **Downstream:** output expressions (`$steps.x.outputs.<name>`, `$outputs.<name>`) and reusable component references.
- **Related:** the official schema already enforces the source-description name pattern via a direct `pattern`; this issue is about the applicators the engine does not support.

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
    outputs:
      "bad key!": $steps.one.outputs.ok
    steps:
      - stepId: one
        operationId: $sourceDescriptions.api.getThing
        outputs:
          "bad key!": $response.body
components:
  parameters:
    "bad key!":
      name: q
      in: query
      value: "1"
```

### 🌀 5. Runtime Execution State Context

- Validation only.
- Observed: the document above reports no error for any `bad key!` key.
- A valid key containing dots, dashes and underscores (e.g. `pets.page-size_2`) must remain accepted.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] Workflow output keys are validated against `^[a-zA-Z0-9\.\-_]+$` with a stable code (suggested: `workflow.outputs_key_pattern`).
- [ ] Step output keys are validated with a stable code (suggested: `step.outputs_key_pattern`); the existing `step.outputs_unique` code may be retained as an alias or deprecated explicitly.
- [ ] `components.inputs`, `components.parameters`, `components.successActions` and `components.failureActions` keys are validated with a stable code (suggested: `components.key_pattern`); repurpose `components.unique_names` or deprecate it explicitly.
- [ ] Error pointers identify the exact key (e.g. `/workflows/0/outputs/bad key!`).
- [ ] Valid keys containing `.`, `-` and `_` are accepted.
- [ ] Pest tests cover workflow outputs, step outputs, each components map, valid keys, and each invalid character class (space, slash, colon, `#`).
- [ ] The two no-op rule tests are replaced with behavioural tests.
- [ ] The rule index/documentation describes the new codes.

### 🧬 7. Source Grounding & Code Location

- `packages/document/src/Validator/Rules/StepOutputsUniqueRule.php:15-23` — registered, empty `check()`.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/document/src/Validator/Rules/StepOutputsUniqueRule.php#L15-L23"><code>StepOutputsUniqueRule.php:15-23</code></a> @ <code>ea8c55a</code></summary>

```php
final class StepOutputsUniqueRule implements Rule
{
    public function code(): string
    {
        return 'step.outputs_unique';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void {}
}
```

</details>

- `packages/document/src/Validator/Rules/ComponentsUniqueNamesRule.php:15-23` — registered, empty `check()`.
- `packages/document/src/Parser/Parser.php:800-809` — `parseOutputsMap()` preserves arbitrary keys.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/document/src/Parser/Parser.php#L800-L809"><code>Parser.php:800-809</code></a> @ <code>ea8c55a</code></summary>

```php
protected function parseOutputsMap(mixed $node, ParseContext $ctx): array
{
    $obj = $this->requireObjectMap($node, $ctx);
    $out = [];
    foreach ($obj as $k => $v) {
        $out[$k] = $this->parseValueOrSelector($v, $ctx->push((string) $k), true);
    }

    return $out;
}
```

</details>

- Spec: Arazzo 1.1.0 §5.8.2 (`workflowOutputs`), §5.8.5.1 (`stepOutputs`), §5.8.6 (components key pattern).

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** none; invalid documents start failing validation.
- **Blast radius:** `packages/document`; output keys are consumed by expression resolution and by the runner.
- **Severity:** medium — malformed outputs surface later as unresolved expressions or missing outputs.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Output keys are not validated | T1 | Executed validator run on the artifact | zero key errors |
| Components keys are not validated | T1 | Executed validator run on the artifact | zero key errors |
| Both rules are empty no-ops | T1 | `StepOutputsUniqueRule.php:22`, `ComponentsUniqueNamesRule.php:22` | direct read |
| The parser preserves arbitrary keys | T1 | `Parser.php:800-809` | direct read |
| The spec requires the regex in all three places | T2 | Arazzo 1.1.0 §5.8.2, §5.8.5.1, §5.8.6 | spec text |
