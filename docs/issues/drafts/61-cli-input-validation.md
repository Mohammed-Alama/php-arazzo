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

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Wire core input validation into `arazzo run` so the CLI validates inputs against the workflow's `inputs` JSON Schema and applies schema defaults before executing.

**Architectural Boundary:** Bounded context = CLI `run` command input handling. Packages: `packages/cli` (`RunCommand` call site), `packages/sources` (`PreflightValidator`), `packages/document` (`Document` facade), `packages/runner` (input scope). CLI port only; core fix is draft 52.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Validation runs once before execution; O(schema size); no overhead for workflows without `inputs` schema.
- [ ] **Flexible:** Works with all input sources; defaults applied before execution; configurable via draft 27 policy.
- [ ] **Maintainable:** `Document::preflightInputs()` facade exists; `PreflightValidator::validateInputs()` has schema logic (draft 52 returns map); CLI only wires call site.
- [ ] **Operable:** Validation fails before any request; stable `preflight.inputs_schema` errors with JSON pointers; non-zero exit on invalid input.
- [ ] **Reliable:** Validated default-populated map used for execution (not raw CLI array); deterministic; no silent acceptance.
- [ ] **Safe:** Read-only validation before execution; prevents wrong requests; no state mutation before validation passes.
- [ ] **Secure:** Input sanitization via schema validation; rejects type mismatches, missing required inputs, enum violations.
- [ ] **Suitable:** P0 correctness fix; closes gap where raw inputs passed to runner without validation.
- [ ] **Usable:** Clear error messages with JSON pointers; defaults visible to `$inputs.*` expressions.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `arazzo run` validates inputs before constructing runner**
- **Given** an Arazzo document (`doc.arazzo`) containing workflow index 0 with `inputs` schema `{ type: "object", required: ["username"] }`; `--input '{"username": "alice"}'` is supplied; `RunCommand` wires input-schema validation before constructing `RunnerFacade` or sending any request.
- **When** `php bin/console arazzo run doc.arazzo --input '{"username": "alice"}'` executes.
- **Then** input-schema validation runs before `RunnerFacade::execute()` is called; `PreflightValidator::validateInputs()` validates the input map; `ValidationResult::isValid()` returns `true`; the validated map is passed to `RunnerFacade::execute()`.

**Scenario 2: Missing required input fails**
- **Given** `--input '{"retries": 5}'` but `username` (required) is missing; `PreflightValidator::validateInputs()` validates against the schema.
- **When** validation runs before execution.
- **Then** fails with `preflight.inputs_schema`; `ErrorCollector` records the error under `errors` with JSON pointer `/properties/username/required`; non-zero exit; `RunnerFacade::execute()` is never called.

**Scenario 3: Type mismatch fails**
- **Given** `--input '{"timeout": "thirty"}'` but `timeout` schema declares `{ type: "integer" }`; `PreflightValidator::validateInputs()` validates the type.
- **When** validation runs.
- **Then** fails with `preflight.inputs_schema`; offending property `/properties/timeout/type` named; message `"Expected type integer, got string"`; non-zero exit.

**Scenario 4: Enum violation fails**
- **Given** `--input '{"region": "mars"}'` but `region` schema declares `{ type: "string", "enum": ["eu", "us"] }`; `PreflightValidator::validateInputs()` validates the enum.
- **When** validation runs.
- **Then** fails with `preflight.inputs_schema`; offending property `/properties/region/enum` named; message `"Value 'mars' is not one of ["eu", "us"]"`; non-zero exit.

**Scenario 5: Validated map used for run (not raw CLI array)**
- **Given** raw CLI input `{"username": "alice", "retries": 999}` and schema default `retries: 3`; `PreflightValidator::validateInputs()` validates and applies defaults.
- **When** `RunnerFacade::execute()` is called.
- **Then** `RunnerFacade` receives validated default-populated map (not raw CLI array); `{"username": "alice", "retries": 3}` is used for execution; valid user input `retries: 999` is preserved if valid (defaults only applied for missing properties).

**Scenario 6: No schema = arbitrary inputs accepted**
- **Given** workflow with no `inputs` schema; `--input '{"anything": "goes"}'` is supplied.
- **When** `arazzo run doc.arazzo --input '{"anything": "goes"}'` executes.
- **Then** accepted (current behavior preserved); `PreflightValidator::validateInputs()` accepts arbitrary input when no schema is declared; `ValidationResult::isValid()` returns `true`.

**Scenario 7: `--dry-run` also validates and applies defaults**
- **Given** `--dry-run` with inputs `--input '{"username": "alice"}'`; workflow has `inputs` schema with required `username` and default `retries: 3`; `DryRunExecutionMode` (draft 49 core) is wired.
- **When** `php bin/console arazzo run doc.arazzo --dry-run --input '{"username": "alice"}'` executes.
- **Then** validation and defaults run before plan; `PreflightValidator::validateInputs()` is called; the plan uses `{"username": "alice", "retries": 3}`; no outbound requests are sent.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `arazzo run` calls input-schema validation before constructing runner or sending request
- [ ] Missing required input fails with `preflight.inputs_schema`; JSON pointer; non-zero exit
- [ ] Type mismatch fails with `preflight.inputs_schema`; offending property
- [ ] Enum violation fails with `preflight.inputs_schema`; offending property
- [ ] Schema defaults applied and visible to request compilation and `$inputs.*` expressions
- [ ] Validated map used for run (not raw CLI array)
- [ ] No `inputs` schema = arbitrary inputs accepted (current behavior)
- [ ] `--dry-run` (draft 58) also validates and applies defaults
- [ ] `RunCommandTest` fixture-tests missing required, type mismatch, enum violation, default injection, no-schema
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Core fix (`PreflightValidator` returns populated map) is draft 52; this draft is CLI port only

### 🧬 10. Source Grounding & Code Location

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

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** none; `RunCommand` is CLI-internal. Behaviour changes from "accepts any input" to "validates before executing".
- **Blast radius:** every CLI run; sub-workflow input propagation if it reuses the same path.
- **Severity:** high — silently wrong requests and missing defaults.
- **Rollback:** revert the call site; validation logic already exists (draft 52 core).

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `RunCommand` never calls input validation | T1 | `RunCommand.php:53-127` | direct read |
| Inputs are passed raw to the runner | T1 | `RunCommand.php:121-122` | direct read |
| Validation logic exists (draft 52 core) | T1 | `PreflightValidator.php:68-139` | direct read |
| Defaults are applied to a discarded object | T1 | `PreflightValidator.php:133-155` | direct read |
| Draft 52 is the core dependency | T2 | draft 52 (input validation returns populated map) | cross-ref |