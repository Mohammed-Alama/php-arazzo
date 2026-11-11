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

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Make `PreflightValidator::validateInputs()` return the default-populated input map instead of discarding it, so schema defaults reach execution and invalid inputs fail at validation time.

**Architectural Boundary:** Bounded context = input validation. Packages: `packages/sources` (`PreflightValidator`), `packages/document` (`Document` facade). Port-agnostic; CLI port tracked in draft 61.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Validation runs once per input map; O(schema size); no overhead for workflows without declared `inputs` schema.
- [ ] **Flexible:** Works with all input sources; defaults applied before execution; configurable via draft 27 policy.
- [ ] **Maintainable:** `Document::preflightInputs()` public facade already exists; `PreflightValidator::validateInputs()` already has schema logic (needs to return populated map); minimal code change.
- [ ] **Operable:** Validation fails before execution; stable `preflight.inputs_schema` error codes with JSON pointers; non-zero exit on invalid input.
- [ ] **Reliable:** Validated default-populated map used for execution (not raw CLI array); deterministic; no silent acceptance.
- [ ] **Safe:** Read-only validation before execution; prevents wrong requests; no state mutation before validation passes.
- [ ] **Secure:** Input sanitization via schema validation; rejects type mismatches, missing required inputs, enum violations.
- [ ] **Suitable:** P0 correctness fix; closes gap where defaults applied to discarded object and raw inputs passed to runner.
- [ ] **Usable:** Clear error messages with JSON pointers; defaults visible to `$inputs.*` expressions and request compilation.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `validateInputs()` returns default-populated map**
- **Given** an Arazzo document (`doc.arazzo`) containing workflow index 0 with `inputs` schema `{ type: "object", required: ["username"], properties: { "username": { "type": "string" }, "retries": { "type": "integer", "default": 3 } } }`; `--input '{"username": "alice"}'` is passed; `PreflightValidator::validateInputs()` (draft 52 core) applies `CHECK_MODE_APPLY_DEFAULTS`.
- **When** `php bin/console arazzo run doc.arazzo --input '{"username": "alice"}'` executes and input-schema validation runs before `RunnerFacade::execute()`.
- **Then** `validateInputs()` returns `{"username": "alice", "retries": 3}`; the default `retries: 3` is injected into the input map; the map is `array<string, mixed>`; `ValidationResult::isValid()` returns `true`.

**Scenario 2: Missing required input fails with `preflight.inputs_schema`**
- **Given** an Arazzo document (`doc.arazzo`) containing workflow index 0 with `inputs` schema `{ type: "object", required: ["username"] }`; `--input '{"retries": 5}'` is passed but `username` (required) is missing.
- **When** `PreflightValidator::validateInputs()` validates the input map against the schema before execution.
- **Then** validation fails with error code `preflight.inputs_schema`; `ErrorCollector` records the error under `errors` with JSON pointer `/properties/username/required` and message `"Required property 'username' is missing"`; `ValidationResult::isValid()` returns `false`; `RunnerFacade::execute()` is never called; the command exits non-zero.

**Scenario 3: Type mismatch fails with `preflight.inputs_schema`**
- **Given** an Arazzo document (`doc.arazzo`) containing workflow index 0 with `inputs` schema `{ type: "object", properties: { "timeout": { "type": "integer" } } }`; `--input '{"timeout": "thirty"}'` is passed (string instead of integer).
- **When** `PreflightValidator::validateInputs()` validates the type of `timeout`.
- **Then** validation fails with error code `preflight.inputs_schema` naming `/properties/timeout/type`; message `"Expected type integer, got string"`; `ValidationResult::isValid()` returns `false`; the command exits non-zero before any request is sent.

**Scenario 4: Enum violation fails with `preflight.inputs_schema`**
- **Given** an Arazzo document (`doc.arazzo`) containing workflow index 0 with `inputs` schema `{ type: "object", properties: { "region": { "type": "string", "enum": ["eu", "us"] } } }`; `--input '{"region": "mars"}'` is passed; `PreflightValidator::validateInputs()` validates the enum constraint.
- **When** validation runs against the input map.
- **Then** validation fails with error code `preflight.inputs_schema` naming `/properties/region/enum`; message `"Value 'mars' is not one of ["eu", "us"]"`; `ValidationResult::isValid()` returns `false`; the command exits non-zero.

**Scenario 5: Defaults visible to expressions and compilation**
- **Given** an Arazzo document (`doc.arazzo`) containing workflow index 0 with `inputs` schema `{ type: "object", properties: { "retries": { "type": "integer", "default": 3 } } }`; `--input '{"username": "alice"}'` is passed without supplying `retries`.
- **When** `PreflightValidator::validateInputs()` applies defaults and returns the populated input map.
- **Then** the default `retries: 3` is injected into the input map; the default is visible to `$inputs.retries` expressions in request compilation; `RunnerFacade::execute()` receives `{"username": "alice", "retries": 3}`.

**Scenario 6: No schema = arbitrary inputs accepted**
- **Given** an Arazzo document (`doc.arazzo`) containing workflow index 0 with no declared `inputs` schema; `--input '{"anything": "goes"}'` is passed.
- **When** `PreflightValidator::validateInputs()` is called with no schema declared on the workflow.
- **Then** arbitrary inputs are accepted (current behavior preserved); `PreflightValidator::validateInputs()` accepts arbitrary input when no schema is declared; `ValidationResult::isValid()` returns `true`; the raw input map is passed through to execution.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `PreflightValidator::validateInputs()` returns default-populated input map (or exposes method that does)
- [ ] Missing required input fails with `preflight.inputs_schema` and JSON pointer
- [ ] Type mismatch fails with `preflight.inputs_schema` and offending property
- [ ] Enum violation fails with `preflight.inputs_schema` and offending property
- [ ] Schema defaults applied and visible to request compilation and `$inputs.*` expressions
- [ ] Workflows with no `inputs` schema accept arbitrary inputs (current behavior preserved)
- [ ] `PreflightValidatorTest` fixture-tests missing required, type mismatch, enum violation, default injection, no-schema
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Error logging via `ErrorCollector` instrumented
- [ ] CLI port (`RunCommand` calls validation before executing) tracked separately in draft 61

### 🧬 10. Source Grounding & Code Location

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

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** `validateInputs()` may need a return shape that includes the populated inputs; additive if a new method is introduced.
- **Blast radius:** every CLI run; sub-workflow input propagation if it reuses the same path.
- **Severity:** high — silently wrong requests and missing defaults.
- **Rollback:** revert the return shape; validation logic already exists.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Validation logic exists | T1 | `PreflightValidator.php:68-139` | direct read |
| Defaults are applied to a discarded object | T1 | `PreflightValidator.php:133-155` | direct read |
| The public facade exists | T1 | `Document.php:48-53` | direct read |
| Defaults are required by the workflow input schema | T2 | JSON Schema 2020-12 `default` is an annotation; callers commonly expect injection | inferred from requested `CHECK_MODE_APPLY_DEFAULTS` |