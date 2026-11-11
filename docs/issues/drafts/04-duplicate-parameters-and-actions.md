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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] A parameter is considered duplicate when another non-reusable parameter in the same list has the same `name` and the same `in` value (including both `null`).
- [ ] Reusable parameter entries are excluded from this check (their identity is the reference; draft 05 covers resolution).
- [ ] A new rule reports duplicates in workflow parameters, step parameters, and action parameters with stable codes and exact pointers.
- [ ] A new rule reports duplicate action names in workflow `successActions`/`failureActions` and step `onSuccess`/`onFailure`.
- [ ] Same `name` with **different** `in` is accepted (e.g. a path parameter and a query parameter both named `id`).
- [ ] The chosen duplicate definition is documented as an interpretation: the spec does not define “duplicate” for actions, so action identity is taken as `name` (consistent with selection by name). Mark this as T3 in the epistemic matrix.
- [ ] Pest tests cover: duplicate query parameter; same name/different `in` accepted; duplicate success action; duplicate failure action; workflow-level and step-level lists; reusable entries ignored.

### 🧬 7. Source Grounding & Code Location

- `packages/engine/src/Execution/StepParameterMerger.php:26-47` — merge identity is `name + in`.
- `packages/document/src/Validator/RuleSet.php:100-160` — registration point.
- Spec: Arazzo 1.1.0 §5.8.2, §5.8.5.1, §5.8.5.2, §5.8.5.3.

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** none; invalid documents start failing validation.
- **Blast radius:** `packages/document`; execution precedence becomes deterministic because duplicates are rejected earlier.
- **Severity:** medium — silent last-wins behaviour is hard to diagnose.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Duplicate `name + in` parameters validate | T1 | Executed validator run on the artifact | zero errors |
| Duplicate action names validate | T1 | Executed validator run on the artifact | zero errors |
| The schema only enforces exact-object uniqueness | T2 | Vendored schema `uniqueItems: true` | direct read |
| Runtime merges parameters by `name + in` | T1 | `StepParameterMerger.php:26-47` | direct read |
| The spec requires non-duplicate lists | T2 | Arazzo 1.1.0 §5.8.2/§5.8.5.1 | spec text |
| Action identity is `name` | T3 | Inference from selection by name; spec does not define it | recorded assumption |
