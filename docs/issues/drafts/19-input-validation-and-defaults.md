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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `arazzo run` calls input-schema validation before constructing the runner or sending any request.
- [ ] A missing required input fails with `preflight.inputs_schema` and a JSON pointer to the property; exit code non-zero.
- [ ] A type mismatch fails with `preflight.inputs_schema` and the offending property.
- [ ] An enum violation fails with `preflight.inputs_schema` and the offending property.
- [ ] Schema defaults are applied and visible to request compilation and to `$inputs.*` expressions.
- [ ] `PreflightValidator::validateInputs()` returns the default-populated input map (or exposes a method that does) rather than discarding it.
- [ ] The validated map is used for the run, not the raw CLI array.
- [ ] Workflows with no declared `inputs` schema accept arbitrary inputs (current behaviour preserved).
- [ ] `--dry-run` also validates and applies defaults.
- [ ] Tests cover missing required, type mismatch, enum violation, default injection and no-schema.
- [ ] PHPStan max and Pint pass.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** `validateInputs()` may need a return shape that includes the populated inputs; additive if a new method is introduced.
- **Blast radius:** every CLI run; sub-workflow input propagation if it reuses the same path.
- **Severity:** high — silently wrong requests and missing defaults.
- **Rollback:** revert the call site; validation logic already exists.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `RunCommand` never calls input validation | T1 | `RunCommand.php:53-127` | direct read |
| Inputs are passed raw to the runner | T1 | `RunCommand.php:121-122` | direct read |
| Validation logic exists | T1 | `PreflightValidator.php:68-139` | direct read |
| Defaults are applied to a discarded object | T1 | `PreflightValidator.php:133-155` | direct read |
| The public facade exists but is unused by the CLI | T1 | `Document.php:48-53`; grep shows no CLI call | direct read + executed |
| Defaults are required by the workflow input schema | T2 | JSON Schema 2020-12 `default` is an annotation; callers commonly expect injection | inferred from requested `CHECK_MODE_APPLY_DEFAULTS` |
