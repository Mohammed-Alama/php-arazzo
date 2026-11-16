# Draft issue 19 — Validate runtime inputs and apply schema defaults in `run`

- **Suggested title:** `[Bug] `arazzo run` skips input schema validation and discards schema defaults`
- **Kind:** `kind:bugfix`
- **Level:** `level:story` (suggested parent: #118 Document & runner validation hardening)
- **Packages:** `cli`, `sources`, `document`, `runner`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `RunCommand`; `packages/sources` `PreflightValidator`; `packages/document` `Document` facade; `packages/runner` input scope.
- **Arazzo spec version:** 1.0.x and 1.1.0.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core CLI.
- **Kind / level:** `kind:bugfix`, `level:story`, under #118 (Document & runner validation hardening).
- **Priority:** P0 — invalid inputs and missing defaults produce wrong behavior silently.

### 📝 2. Problem Statement & Intent

The validator can check runtime inputs against a workflow's declared `inputs`
JSON Schema: `PreflightValidator::validateInputs()` validates the supplied array
and passes `CHECK_MODE_APPLY_DEFAULTS`
(`packages/sources/src/Validator/PreflightValidator.php:68-139`). It is exposed
through `Document::preflightInputs()` (`packages/document/src/Document.php:48-53`).

`RunCommand`, however:

1. parses `--input` JSON and checks only that it is an object
   (`RunCommand.php:140-158`);
2. never calls `preflightInputs()`;
3. passes the raw array straight to `RunnerFacade::execute()`
   (`RunCommand.php:121-122`).

Two defects follow:

- **No validation.** A missing required input or a type mismatch is accepted; the
  failure surfaces later as an unresolved expression or a wrong request, with a
  misleading error.
- **No defaults.** `validateAgainstSchema()` asks the JSON Schema validator to
  apply defaults to its decoded copy, but the method returns only error strings
  and discards the mutated `$dataObject`
  (`PreflightValidator.php:133-155`). Defaults declared in the workflow `inputs`
  schema therefore never reach execution — even if `validateInputs()` were
  called.

Expected: `arazzo run` validates inputs before any side effect and uses the
validated, default-populated input map for execution.

### 📦 3. System Dependency Matrix

- **Upstream:** `justinrainbow/json-schema` (`Validator`, `SchemaStorage`, `Constraint`); `ArazzoDocument` workflow `inputs` schema; `PreflightValidator`.
- **Downstream:** request compilation, expression evaluation, sub-workflow input propagation, all CLI run modes (drafts 12-18).
- **Internal seams:** `Document::preflightInputs()` already exists; `PreflightValidator::validateInputs()` already has the schema logic.
- **Packages touched:** `packages/sources`, `packages/document`, `packages/cli`.

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

```bash
# Accepted today, should fail: `name` is missing and `region` is invalid.
arazzo run inputs.arazzo.yaml -w create -i '{"region":"mars"}'

# Accepted today, should inject retries=3.
arazzo run inputs.arazzo.yaml -w create -i '{"name":"Rex"}'
```

### 🌀 5. Runtime Execution State Context

- Workflow: the selected workflow.
- Step: the first step that reads an input.
- Inputs: parsed from `--input` / `-i`.
- Environment: any run mode, including `--dry-run` (draft 16).
- State: `WorkflowContext` is constructed from the raw inputs; no validator runs before it.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Make `arazzo run` validate runtime inputs against the workflow's declared `inputs` JSON Schema and apply schema defaults, so invalid inputs and missing defaults do not produce wrong behavior silently.

**Architectural Boundary:** Bounded context = CLI input handling + validation. Packages: `packages/cli` (`RunCommand` call site), `packages/sources` (`PreflightValidator`), `packages/document` (`Document` facade), `packages/runner` (input scope). Core fix (validator returns populated map) is draft 52; this draft is the CLI port only.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Validation runs once before execution; O(schema size); no overhead for workflows without declared `inputs` schema.
- [ ] **Flexible:** Works with all input sources (inline JSON, `@file`, `key=value` from draft 41); defaults applied before execution; configurable via draft 27 policy.
- [ ] **Maintainable:** `Document::preflightInputs()` public facade already exists; `PreflightValidator::validateInputs()` already has schema logic (draft 52 core returns the populated map); CLI only wires the call site.
- [ ] **Operable:** Validation fails before any request; stable error codes (`preflight.inputs_schema`) with JSON pointers; exit code non-zero on invalid input.
- [ ] **Reliable:** Validated, default-populated map is used for execution (not raw CLI array); deterministic; no silent acceptance of invalid inputs.
- [ ] **Safe:** Validation is read-only before execution; prevents wrong requests from being sent; no state mutation before validation passes.
- [ ] **Secure:** Input sanitization via schema validation; rejects type mismatches, missing required inputs, enum violations before execution.
- [ ] **Suitable:** P0 correctness fix; closes the gap where `validateInputs()` applied defaults to a discarded object and `RunCommand` passed raw inputs to the runner.
- [ ] **Usable:** Clear error messages with JSON pointers to offending properties; defaults visible to `$inputs.*` expressions and request compilation.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `arazzo run` validates inputs before any request**
- **Given** an Arazzo 1.1.0 document (`doc.arazzo`) containing workflow index 0 with `inputs` schema `{ type: "object", required: ["username"], properties: { "username": { "type": "string" }, "retries": { "type": "integer", "default": 3 } } }`; `--input '{"username": "alice"}'` is passed; `PreflightValidator::validateInputs()` (draft 52 core) returns the default-populated input map; `RunCommand` wires the validation call site before `RunnerFacade::execute()`.
- **When** `php bin/console arazzo run doc.arazzo --input '{"username": "alice"}'` executes.
- **Then** input-schema validation runs before constructing `RunnerFacade` or sending any request; the validated map `{ "username": "alice", "retries": 3 }` is used for execution; `PreflightValidator::validateInputs()` applies schema defaults (retries: 3); `ValidationResult::isValid()` returns `true`.

**Scenario 2: Missing required input fails with `preflight.inputs_schema`**
- **Given** `--input '{"retries": 5}'` is passed but `username` (required) is missing; `PreflightValidator::validateInputs()` validates against the `inputs` JSON Schema.
- **When** validation runs before execution.
- **Then** validation fails with error code `preflight.inputs_schema`; `ErrorCollector` records the error under `errors` with JSON pointer `/properties/username/required` and message `"Required property 'username' is missing"`; the command exits with a non-zero code before any request is sent.

**Scenario 3: Type mismatch fails with `preflight.inputs_schema`**
- **Given** `--input '{"username": 123}'` is passed but `username` schema declares `{ "type": "string" }`; `PreflightValidator::validateInputs()` validates the type.
- **When** validation runs.
- **Then** validation fails with error code `preflight.inputs_schema` naming the offending property `/properties/username/type`; message `"Expected type string, got integer"`; `ValidationResult::isValid()` returns `false`; non-zero exit.

**Scenario 4: Enum violation fails with `preflight.inputs_schema`**
- **Given** `--input '{"region": "mars"}'` is passed but `region` schema declares `{ "type": "string", "enum": ["eu", "us"] }`; `PreflightValidator::validateInputs()` validates the enum.
- **When** validation runs.
- **Then** validation fails with error code `preflight.inputs_schema` naming `/properties/region/enum`; message `"Value 'mars' is not one of [\"eu\", \"us\"]"`; `ValidationResult::isValid()` returns `false`.

**Scenario 5: Schema defaults are applied and visible**
- **Given** `--input '{"username": "alice"}'` is passed but `retries` is not supplied; `retries` schema declares `{ "type": "integer", "default": 3 }`; `PreflightValidator::validateInputs()` applies `CHECK_MODE_APPLY_DEFAULTS`.
- **When** validation runs and returns the populated map.
- **Then** the default `retries: 3` is injected into the input map; the default is visible to request compilation (`$inputs.retries` in request body) and to `$inputs.*` expressions; `RunnerFacade::execute()` receives `{ "username": "alice", "retries": 3 }`.

**Scenario 6: The validated map is used for the run**
- **Given** raw CLI input `{"username": "alice", "retries": 999}` and schema default `retries: 3`; `PreflightValidator::validateInputs()` validates and applies defaults.
- **When** `RunnerFacade::execute()` is called.
- **Then** `RunnerFacade::execute()` receives the validated, default-populated map (not the raw CLI array); the raw `retries: 999` is preserved if valid (validation doesn't override valid user input); defaults are only applied for missing properties.

**Scenario 7: Workflows with no `inputs` schema accept arbitrary inputs**
- **given** an Arazzo document with no declared `inputs` schema on workflow index 0; `--input '{"anything": "goes"}'` is passed.
- **When** `arazzo run doc.arazzo --input '{"anything": "goes"}'` executes.
- **Then** arbitrary inputs are accepted (current behaviour preserved); `PreflightValidator::validateInputs()` accepts arbitrary input when no schema is declared; `ValidationResult::isValid()` returns `true`.

**Scenario 8: `--dry-run` also validates and applies defaults**
- **Given** `--dry-run` with `--input '{"username": "alice"}'`; workflow has `inputs` schema with required `username` and default `retries: 3`; `DryRunExecutionMode` (draft 49 core) is wired.
- **When** `php bin/console arazzo run doc.arazzo --dry-run --input '{"username": "alice"}'` executes.
- **Then** validation and default injection run before producing the request plan; `PreflightValidator::validateInputs()` is called; the plan uses `{ "username": "alice", "retries": 3 }`; no outbound requests are sent.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `arazzo run` calls input-schema validation before constructing the runner or sending any request
- [ ] Missing required input fails with `preflight.inputs_schema` and JSON pointer; non-zero exit
- [ ] Type mismatch fails with `preflight.inputs_schema` and offending property
- [ ] Enum violation fails with `preflight.inputs_schema` and offending property
- [ ] Schema defaults applied and visible to request compilation and `$inputs.*` expressions
- [ ] `PreflightValidator::validateInputs()` returns the default-populated input map (draft 52 core)
- [ ] The validated map is used for the run, not the raw CLI array
- [ ] Workflows with no declared `inputs` schema accept arbitrary inputs (current behaviour preserved)
- [ ] `--dry-run` (draft 16) also validates and applies defaults
- [ ] Tests cover missing required, type mismatch, enum violation, default injection and no-schema
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Error logging via existing CLI error handling
- [ ] Core fix (`PreflightValidator` returns populated map) is draft 52; this draft is the CLI port only

### 🧬 10. Source Grounding & Code Location

- `packages/cli/src/Console/Command/RunCommand.php:68-73` and `:121-122` — inputs parsed and passed raw.
- `packages/cli/src/Console/Command/RunCommand.php:140-158` — only checks that `--input` is an object.
- `packages/sources/src/Validator/PreflightValidator.php:68-139` — validation exists, defaults requested but discarded.
- `packages/document/src/Document.php:48-53` — public facade for input validation.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/sources/src/Validator/PreflightValidator.php#L132-L155"><code>PreflightValidator.php:132-155</code></a> @ <code>ea8c55a</code></summary>

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
- **Rollback:** revert the call site; validation logic already exists.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `RunCommand` never calls input validation | T1 | `RunCommand.php:53-127` | direct read |
| Inputs are passed raw to the runner | T1 | `RunCommand.php:121-122` | direct read |
| Validation logic exists | T1 | `PreflightValidator.php:68-139` | direct read |
| Defaults are applied to a discarded object | T1 | `PreflightValidator.php:133-155` | direct read |
| The public facade exists but is unused by the CLI | T1 | `Document.php:48-53`; grep shows no CLI call | direct read + executed |
| Defaults are required by the workflow input schema | T2 | JSON Schema 2020-12 `default` is an annotation; callers commonly expect injection | inferred from requested `CHECK_MODE_APPLY_DEFAULTS` |
