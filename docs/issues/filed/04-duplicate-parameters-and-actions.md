# Draft issue 04 — Detect duplicate parameters and actions

- **Suggested title:** `[Bug] Duplicate parameters and success/failure actions are not rejected`
- **Kind:** `kind:bugfix`
- **Level:** `level:story` (suggested parent: #118 Document & runner validation hardening)
- **Packages:** `document`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/document` validator.
- **Arazzo spec version:** 1.0.x and 1.1.x.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core.
- **Kind / level:** `kind:bugfix`, `level:story`.
- **Priority:** medium-high — spec MUST, with ambiguous runtime precedence when violated.

### 📝 2. Problem Statement & Intent

The spec says the following lists **MUST NOT include duplicate** entries:

- Workflow `parameters`, `successActions`, `failureActions` (§5.8.2).
- Step `parameters`, `onSuccess`, `onFailure` (§5.8.5.1).
- Action `parameters` (§5.8.5.2/§5.8.5.3).

php-arazzo has no semantic duplicate check. The official schema sets `uniqueItems: true`, but that only rejects **structurally identical** JSON objects. Two parameters with the same `name` and `in` but different `value`s are distinct JSON objects, and two actions with the same `name` but different criteria are likewise distinct. Both validate successfully.

This matters at runtime: `StepParameterMerger` merges by `name + in`, and action selection is by action name, so duplicates silently produce last-wins or first-match behaviour rather than a validation error.

### 📦 3. System Dependency Matrix

- **Upstream:** `packages/contracts` (`Parameter`, `Reusable`, `Action`, `Workflow`, `Step`).
- **Validator:** new rule(s) under `packages/document/src/Validator/Rules/`, registered in `RuleSet`.
- **Runtime:** `packages/engine/src/Execution/StepParameterMerger.php` (merge key is `name + in`); action selection by action name.
- **Related:** #79 covers an action declaring both `stepId` and `workflowId`; this issue is about repeated entries in a list.

### 📄 4. Structural Artifacts & Raw Inputs

Duplicate parameter (`name + in`):

```yaml
arazzo: 1.1.0
info: { title: T, version: "1.0.0" }
sourceDescriptions:
  - name: api
    url: https://example.com/openapi.yaml
    type: openapi
workflows:
  - workflowId: wf
    steps:
      - stepId: one
        operationId: $sourceDescriptions.api.getThing
        parameters:
          - { name: q, in: query, value: "a" }
          - { name: q, in: query, value: "b" }
```

Duplicate success actions (same `name`):

```yaml
arazzo: 1.1.0
info: { title: T, version: "1.0.0" }
sourceDescriptions:
  - name: api
    url: https://example.com/openapi.yaml
    type: openapi
workflows:
  - workflowId: wf
    steps:
      - stepId: one
        operationId: $sourceDescriptions.api.getThing
        onSuccess:
          - { name: go, type: end, criteria: [ { condition: "$statusCode == 200" } ] }
          - { name: go, type: end, criteria: [ { condition: "$statusCode == 201" } ] }
```

### 🌀 5. Runtime Execution State Context

- Validation only.
- Observed: both documents validate with **zero errors**.
- At execution time, `StepParameterMerger::sameTarget()` considers `name + in` the identity, and action selection uses the first matching action name.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Detect duplicate parameters and success/failure actions in lists, rejecting documents that the spec says MUST NOT include duplicate entries, preventing ambiguous last-wins / first-match runtime precedence.

**Architectural Boundary:** Bounded context = document validation. Package affected: `packages/document` (new validator rules registered in `RuleSet`). Runtime merge key is `name + in` in `StepParameterMerger`; action selection is by action name.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Validation-only; duplicate detection via hash set is O(n) per list; no runtime execution impact.
- [ ] **Flexible:** Rules additive to `RuleSet::default()`; configurable via draft 27 policy (disable/severity).
- [ ] **Maintainable:** Single-responsibility rules; exact pointers to duplicate entries; logged via `ErrorCollector`.
- [ ] **Operable:** `arazzo validate` emits deterministic duplicate errors; no deployment changes.
- [ ] **Reliable:** Deterministic validation; idempotent; no side effects.
- [ ] **Safe:** Read-only validation; prevents silent content override at runtime.
- [ ] **Secure:** Input validation strengthens conformance; reduces ambiguity in parameter/action resolution.
- [ ] **Suitable:** Implements Arazzo 1.1.0 §5.8.2, §5.8.5.1, §5.8.5.2, §5.8.5.3 (lists MUST NOT include duplicates).
- [ ] **Usable:** Precise error pointers name the duplicate and its locations; action identity documented as `name`.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: Duplicate parameter (`name` + `in`) is rejected**
- **Given** an Arazzo 1.1.0 document (`doc.arazzo`) containing workflow index 0, step index 0 with `parameters: [{ name: "q", in: "query", value: "a" }, { name: "q", in: "query", value: "b" }]`; both entries are non-reusable (no `reference` field); a new parameter uniqueness rule is registered in `RuleSet::default()` with code `step.parameters_duplicate`.
- **When** `php bin/console arazzo validate doc.arazzo` invokes `Validator::validate()` against the parsed `ArazzoDocument`.
- **Then** validation fails with error code `step.parameters_duplicate` at pointer `/workflows/0/steps/0/parameters`; `ErrorCollector` records the error under `errors`; the error message names the duplicate parameter `name: q, in: query` and the indices of both entries (0 and 1); `ValidationResult::isValid()` returns `false`.

**Scenario 2: Duplicate action names in onSuccess are rejected**
- **Given** an Arazzo 1.1.0 document containing workflow index 0, step index 0 with `onSuccess: [{ name: "go", type: "end", criteria: [...] }, { name: "go", type: "end", criteria: [...] }]`; both actions share name `go`; a new action uniqueness rule is registered with code `step.actions_duplicate`.
- **When** `Validator::validate()` applies the action uniqueness rule to `StepFlow::$onSuccess`.
- **Then** validation fails with error code `step.actions_duplicate` at pointer `/workflows/0/steps/0/onSuccess`; `ErrorCollector` records the error under `errors`; the error message names the duplicate action name `go`; `ValidationResult::isValid()` returns `false`.

**Scenario 3: Same `name` with different `in` is accepted**
- **Given** an Arazzo 1.1.0 document containing workflow index 0, step index 0 with `parameters: [{ name: "id", in: "path", value: "1" }, { name: "id", in: "query", value: "2" }]`; both entries are non-reusable; the duplicate definition treats identity as `name + in` (same name, different `in` is not a duplicate).
- **When** `Validator::validate()` applies the parameter uniqueness rule.
- **Then** validation passes; `ValidationResult::isValid()` returns `true`; `ErrorCollector` records zero errors; path and query parameters may share a name.

**Scenario 4: Reusable parameter entries are excluded from duplicate check**
- **Given** an Arazzo 1.1.0 document containing workflow index 0, step index 0 with `parameters: [{ reference: "$components.parameters.x" }, { reference: "$components.parameters.x" }]`; both entries are reusable (`reference` field set, no `in` field); per draft 05, reusable entries are skipped (identity is the reference).
- **When** `Validator::validate()` applies the parameter uniqueness rule.
- **Then** validation passes; `ValidationResult::isValid()` returns `true`; reusable entries are skipped; no duplicate error is reported for identical references.

**Scenario 5: Workflow-level and step-level duplicate checks both apply**
- **Given** an Arazzo 1.1.0 document with duplicate parameters `{ name: "q", in: "query" }` appearing twice in both workflow-level `parameters` and step index 0 `parameters`.
- **When** `Validator::validate()` applies the parameter uniqueness rule to both `Workflow::$parameters` and `Step::$parameters`.
- **Then** validation fails in both cases; `ErrorCollector` reports errors at pointers `/workflows/0/parameters` and `/workflows/0/steps/0/parameters`; `ValidationResult::isValid()` returns `false`.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] New rule reports duplicate parameters when same `name` and same `in` (including both `null`) appear in the same list
- [ ] Reusable parameter entries excluded from the duplicate check
- [ ] New rule reports duplicate action names in workflow `successActions`/`failureActions` and step `onSuccess`/`onFailure`
- [ ] Same `name` with different `in` is accepted
- [ ] Duplicate definition documented as an interpretation (spec does not define "duplicate" for actions; identity taken as `name`); marked T3 in epistemic matrix
- [ ] Stable error codes and exact pointers for all duplicate cases
- [ ] Rules registered in `RuleSet::default()`
- [ ] Pest tests: duplicate query parameter; same name/different `in` accepted; duplicate success action; duplicate failure action; workflow-level and step-level lists; reusable entries ignored
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Error logging via `ErrorCollector` instrumented


### 🧬 10. Source Grounding & Code Location

- `packages/engine/src/Execution/StepParameterMerger.php:26-47` — merge identity is `name + in`.
- `packages/document/src/Validator/RuleSet.php:100-160` — registration point.
- Spec: Arazzo 1.1.0 §5.8.2, §5.8.5.1, §5.8.5.2, §5.8.5.3.

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** none; invalid documents start failing validation.
- **Blast radius:** `packages/document`; execution precedence becomes deterministic because duplicates are rejected earlier.
- **Severity:** medium — silent last-wins behaviour is hard to diagnose.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Duplicate `name + in` parameters validate | T1 | Executed validator run on the artifact | zero errors |
| Duplicate action names validate | T1 | Executed validator run on the artifact | zero errors |
| The schema only enforces exact-object uniqueness | T2 | Vendored schema `uniqueItems: true` | direct read |
| Runtime merges parameters by `name + in` | T1 | `StepParameterMerger.php:26-47` | direct read |
| The spec requires non-duplicate lists | T2 | Arazzo 1.1.0 §5.8.2/§5.8.5.1 | spec text |
| Action identity is `name` | T3 | Inference from selection by name; spec does not define it | recorded assumption |
