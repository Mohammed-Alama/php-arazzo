# Draft issue 61 — CLI: `run` validates inputs and applies schema defaults

- **Suggested title:** `[Bug] `arazzo run` skips input schema validation and discards schema defaults`
- **Kind:** `kind:bugfix`
- **Level:** `level:story` (suggested parent: #118 Document & runner validation hardening)
- **Packages:** `cli`, `sources`, `document`, `runner`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `RunCommand`; `packages/sources` `PreflightValidator`; `packages/document` `Document` facade; `packages/runner` input scope.
- **Arazzo spec version:** 1.0.x and 1.1.0.
- **PHP target:** 8.4.
- **Ecosystem:** CLI entry point only; the core input-validation fix lives in draft 52.
- **Kind / level:** `kind:bugfix`, `level:story`, under #118 (Document & runner validation hardening).
- **Priority:** P0 — invalid inputs and missing defaults produce wrong behavior silently.

### 📝 2. Problem Statement & Intent

Draft 52 core fixes `PreflightValidator::validateInputs()` to return the default-populated input map. This draft wires it into the CLI:

- `RunCommand` parses `--input` JSON and checks only that it is an object (`RunCommand.php:140-158`).
- It never calls `preflightInputs()`.
- It passes the raw array straight to `RunnerFacade::execute()` (`RunCommand.php:121-122`).

Two defects follow:

- **No validation.** A missing required input or a type mismatch is accepted; the failure surfaces later as an unresolved expression or a wrong request, with a misleading error.
- **No defaults.** Schema defaults declared in the workflow `inputs` schema never reach execution.

Expected: `arazzo run` validates inputs before any side effect and uses the validated, default-populated input map for execution.

### 📦 3. System Dependency Matrix

- **Upstream:** draft 52 core (`PreflightValidator::validateInputs()` returns default-populated map); `Document::preflightInputs()` public facade.
- **Downstream:** request compilation, expression evaluation, sub-workflow input propagation, all CLI run modes (drafts 45-60).
- **Internal seams:** `Document::preflightInputs()` already exists; `PreflightValidator::validateInputs()` already has the schema logic (draft 52 core).
- **Packages touched:** `packages/cli` (primary: `RunCommand` call site), `packages/sources`, `packages/document` (read-only: draft 52 core).
- **Scope boundary:** CLI call site only. Draft 52 core is the prerequisite.

### 📄 4. Structural Artifacts & Raw Inputs

```bash
# Accepted today, should fail: `name` is missing and `region` is invalid.
arazzo run inputs.arazzo.yaml -w create -i '{"region":"mars"}'

# Accepted today, should inject retries=3.
arazzo run inputs.arazzo.yaml -w create -i '{"name":"Rex"}'
```

### 🌀 5. Runtime Execution State Context

- Command: `arazzo run <file> -w <workflowId> -i <json>`.
- `RunCommand` parses `--input`, calls `Document::preflightInputs()`, then passes the validated map to `RunnerFacade::execute()`.
- State: `WorkflowContext` is constructed from the validated inputs map; no validator runs before it today.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `arazzo run` calls input-schema validation before constructing the runner or sending any request.
- [ ] A missing required input fails with `preflight.inputs_schema` and a JSON pointer to the property; exit code non-zero.
- [ ] A type mismatch fails with `preflight.inputs_schema` and the offending property.
- [ ] An enum violation fails with `preflight.inputs_schema` and the offending property.
- [ ] Schema defaults are applied and visible to request compilation and to `$inputs.*` expressions.
- [ ] The validated map is used for the run, not the raw CLI array.
- [ ] Workflows with no declared `inputs` schema accept arbitrary inputs (current behaviour preserved).
- [ ] `--dry-run` (draft 58) also validates and applies defaults.
- [ ] `RunCommandTest` fixture-tests missing required, type mismatch, enum violation, default injection and no-schema.
- [ ] PHPStan max and Pint pass.
- [ ] **Core fix** (`PreflightValidator` returns populated map) is draft 52; this draft is the CLI port only.

### 🧬 7. Source Grounding & Code Location

- `packages/cli/src/Console/Command/RunCommand.php:68-73` and `:121-122` — inputs parsed and passed raw.
- `packages/cli/src/Console/Command/RunCommand.php:140-158` — only checks that `--input` is an object.
- `packages/sources/src/Validator/PreflightValidator.php:68-139` — validation exists, defaults requested but discarded (draft 52 core).
- `packages/document/src/Document.php:48-53` — public facade for input validation.

<details><summary><code>RunCommand.php:140-158</code></summary>

```php
/** @var string|null $rawInput */
$rawInput = $input->getOption('input');
$inputs = $this->parseInputs($rawInput, $output);
if ($inputs === null) {
    return Command::FAILURE;
}
// ... only checks $inputs is an array/object, then passes raw to RunnerFacade
```

</details>

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** none; `RunCommand` is CLI-internal. Behaviour changes from "accepts any input" to "validates before executing".
- **Blast radius:** every CLI run; sub-workflow input propagation if it reuses the same path.
- **Severity:** high — silently wrong requests and missing defaults.
- **Rollback:** revert the call site; validation logic already exists (draft 52 core).

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `RunCommand` never calls input validation | T1 | `RunCommand.php:53-127` | direct read |
| Inputs are passed raw to the runner | T1 | `RunCommand.php:121-122` | direct read |
| Validation logic exists (draft 52 core) | T1 | `PreflightValidator.php:68-139` | direct read |
| Defaults are applied to a discarded object | T1 | `PreflightValidator.php:133-155` | direct read |
| Draft 52 is the core dependency | T2 | draft 52 (input validation returns populated map) | cross-ref |