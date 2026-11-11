# Draft issue 52 — Input validation returns default-populated input map

- **Suggested title:** `[Bug] Input-schema validation discards schema defaults instead of returning them`
- **Kind:** `kind:bugfix`
- **Level:** `level:story` (suggested parent: #118 Document & runner validation hardening)
- **Packages:** `sources`, `document`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/sources` `PreflightValidator`; `packages/document` `Document` facade.
- **Arazzo spec version:** 1.0.x and 1.1.0.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core; port-agnostic (usable from CLI, MCP, or UI).
- **Kind / level:** `kind:bugfix`, `level:story`, under #118 (Document & runner validation hardening).
- **Priority:** P0 — invalid inputs and missing defaults produce wrong behavior silently.

### 📝 2. Problem Statement & Intent

The validator can check runtime inputs against a workflow's declared `inputs` JSON Schema: `PreflightValidator::validateInputs()` validates the supplied array and passes `CHECK_MODE_APPLY_DEFAULTS` (`packages/sources/src/Validator/PreflightValidator.php:68-139`). It is exposed through `Document::preflightInputs()` (`packages/document/src/Document.php:48-53`).

Two defects follow:

- `validateInputs()` validates but returns only error strings. The validated, default-populated input map is discarded.
- `validateAgainstSchema()` asks the JSON Schema validator to apply defaults to its decoded copy, but the method returns only error strings and discards the mutated `$dataObject` (`PreflightValidator.php:133-155`). Defaults declared in the workflow `inputs` schema therefore never reach execution.

Expected: `validateInputs()` returns the default-populated input map so any entry point (CLI, MCP, UI) can use it for execution. The CLI port (draft 61) wires it into `RunCommand`.

### 📦 3. System Dependency Matrix

- **Upstream:** `justinrainbow/json-schema` (`Validator`, `SchemaStorage`, `Constraint`); `ArazzoDocument` workflow `inputs` schema; `PreflightValidator`.
- **Downstream:** request compilation, expression evaluation, sub-workflow input propagation, all run modes (drafts 45-51 core).
- **Internal seams:** `Document::preflightInputs()` already exists; `PreflightValidator::validateInputs()` already has the schema logic.
- **Packages touched:** `packages/sources`, `packages/document`.

### 📄 4. Structural Artifacts & Raw Inputs

```yaml
arazzo: 1.0.1
info: { title: Inputs, version: "1" }
sourceDescriptions:
  - { name: petstore, url: ./openapi.yaml, type: openapi }
workflows:
  - workflowId: create
    inputs:
      type: object
      required: [name]
      properties:
        name: { type: string }
        retries: { type: integer, default: 3 }
        region: { type: string, enum: [eu, us] }
    steps:
      - stepId: create
        operationId: createPet
        requestBody:
          payload:
            name: $inputs.name
            retries: $inputs.retries
```

### 🌀 5. Runtime Execution State Context

- Workflow: the selected workflow.
- Step: the first step that reads an input.
- Inputs: supplied by the entry point (CLI `--input`, MCP/UI equivalent).
- State: `WorkflowContext` is constructed from the inputs map; no validator runs before it today.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `PreflightValidator::validateInputs()` returns the default-populated input map (or exposes a method that does) rather than discarding it.
- [ ] A missing required input fails with `preflight.inputs_schema` and a JSON pointer to the property.
- [ ] A type mismatch fails with `preflight.inputs_schema` and the offending property.
- [ ] An enum violation fails with `preflight.inputs_schema` and the offending property.
- [ ] Schema defaults are applied and visible to request compilation and to `$inputs.*` expressions.
- [ ] Workflows with no declared `inputs` schema accept arbitrary inputs (current behaviour preserved).
- [ ] `PreflightValidatorTest` fixture-tests missing required, type mismatch, enum violation, default injection and no-schema.
- [ ] PHPStan max and Pint pass.
- [ ] **CLI port** (`RunCommand` calls validation before executing) is tracked separately in draft 61.

### 🧬 7. Source Grounding & Code Location

- `packages/sources/src/Validator/PreflightValidator.php:68-139` — validation exists, defaults requested but discarded.
- `packages/sources/src/Validator/PreflightValidator.php:132-155` — defaults applied to a discarded object.
- `packages/document/src/Document.php:48-53` — public facade for input validation.

<details><summary><code>PreflightValidator.php:132-155</code></summary>

```php
$validator = new Validator();
$dataObject = \json_decode((string) \json_encode($inputs ?: new \stdClass()), false);

$validator->validate(
    $dataObject,
    $storage->getSchema($schemaId),
    Constraint::CHECK_MODE_VALIDATE_SCHEMA | Constraint::CHECK_MODE_APPLY_DEFAULTS,
);

if ($validator->isValid()) {
    return [];
}

$out = [];

foreach ($validator->getErrors() as $error) {
    $out[] = [
        'message' => (string) $error['message'],
        'property' => is_string($error['property'] ?? null) ? $error['property'] : '',
    ];
}

return $out;
```

</details>

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** `validateInputs()` may need a return shape that includes the populated inputs; additive if a new method is introduced.
- **Blast radius:** every CLI run; sub-workflow input propagation if it reuses the same path.
- **Severity:** high — silently wrong requests and missing defaults.
- **Rollback:** revert the return shape; validation logic already exists.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Validation logic exists | T1 | `PreflightValidator.php:68-139` | direct read |
| Defaults are applied to a discarded object | T1 | `PreflightValidator.php:133-155` | direct read |
| The public facade exists | T1 | `Document.php:48-53` | direct read |
| Defaults are required by the workflow input schema | T2 | JSON Schema 2020-12 `default` is an annotation; callers commonly expect injection | inferred from requested `CHECK_MODE_APPLY_DEFAULTS` |