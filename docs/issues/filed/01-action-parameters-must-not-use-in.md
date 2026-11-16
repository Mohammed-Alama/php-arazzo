# Draft issue 01 — Reject `in` on action parameters

- **Suggested title:** `[Bug] Validator accepts 'in' on Success/Failure Action parameters`
- **Kind:** `kind:bugfix`
- **Level:** `level:story` (suggested parent: #118 Document & runner validation hardening)
- **Packages:** `document`, `contracts`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/document` (parser + validator), `packages/contracts` (`Parameter`, `ParameterIn`).
- **Arazzo spec version:** 1.1.0 (action `parameters` exist only in 1.1; 1.0 has no action `parameters` field).
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core.
- **Kind / level:** `kind:bugfix`, `level:story`.
- **Priority:** high — direct spec MUST violation.

### 📝 2. Problem Statement & Intent

The Arazzo 1.1 Success Action Object and Failure Action Object both define `parameters` as “a list of parameters that MUST be passed to a workflow as referenced by `workflowId`”, and both state: **“The `in` field MUST NOT be used.”**

php-arazzo does not enforce this. `Parser::parseActionParameters()` delegates every entry to `parseParameter()`, which accepts the optional `in` field and stores it on the `Parameter` DTO. No validator rule inspects action parameters, so a document with `in: query` on a goto action validates successfully.

The same gap exists for the 1.0 field itself: the 1.0 Success/Failure Action schemas do not define `parameters` at all, but the vendored schema validator does not enforce `unevaluatedProperties`, so a 1.0 document carrying action `parameters` is also accepted.

### 📦 3. System Dependency Matrix

- **Upstream:** `packages/contracts` (`ParameterIn`, `Parameter`, `SuccessGotoAction`, `FailureGotoAction`, `RetryAction`).
- **Parser:** `packages/document/src/Parser/Parser.php` — `parseActionParameters()` and `parseParameter()`.
- **Validator:** new rule in `packages/document/src/Validator/Rules/`, registered in `RuleSet`.
- **Runner:** action parameters are consumed by `packages/runner/src/Sync/WorkflowEngine.php`; the rule must not reject reusable parameter references.
- **Related work:** #171 covers beyond-spec `in` values (`body`, `metadata`, `variable`) generally. This issue is narrower and unconditional: **any** `in` on an action parameter is forbidden, regardless of value.

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
    steps:
      - stepId: one
        operationId: $sourceDescriptions.api.getThing
        onSuccess:
          - name: go
            type: goto
            workflowId: other
            parameters:
              - name: x
                in: query      # forbidden
                value: "1"
  - workflowId: other
    steps:
      - stepId: two
        operationId: $sourceDescriptions.api.getThing
```

### 🌀 5. Runtime Execution State Context

- Validation only; no execution required.
- Command: parse the document, then `Validator(RuleSet::default(new ExpressionEngine()))->validate($doc)`.
- Observed: **zero errors**. The same result occurs for a `failureActions` retry action and for a 1.0 document carrying action `parameters`.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Enforce the Arazzo 1.1 spec MUST that the `in` field MUST NOT be used on Success/Failure Action parameters, closing a validator gap that lets invalid documents pass validation and introduces ambiguous runtime precedence (last-wins / first-match).

**Architectural Boundary:** Bounded context = document validation. Packages affected: `packages/document` (parser + validator rules), `packages/contracts` (`Parameter`, `ParameterIn`, action DTOs). Validation only — no runtime execution changes.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** No runtime overhead; validation-only rule adds O(n) parameter scan per document; zero impact on execution throughput.
- [ ] **Flexible:** Rule is additive to `RuleSet::default()`; configurable via draft 27 policy (disable/severity overrides per rule code).
- [ ] **Maintainable:** Single-responsibility rule class; deterministic error pointer identifies exact `parameters/{m}/in` location; findings logged via `ErrorCollector` for consistent output format.
- [ ] **Operable:** `arazzo validate` emits deterministic, stable error codes; no new deployment, telemetry, or rollback requirements.
- [ ] **Reliable:** Deterministic validation result; idempotent across repeated runs; no side effects; no state mutation.
- [ ] **Safe:** Validation is read-only; no state corruption or side-effect isolation concerns.
- [ ] **Secure:** No authorization/ACL impact; input validation strengthens spec conformance and reduces attack surface from malformed documents.
- [ ] **Suitable:** Directly implements Arazzo 1.1.0 §5.8.5.2/§5.8.5.3 ("The `in` field MUST NOT be used" on action parameters); closes conformance gap.
- [ ] **Usable:** Precise error pointer (`/workflows/{i}/steps/{j}/onSuccess|onFailure/{k}/parameters/{m}/in`) aids author debugging; stable error code enables tooling integration.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: Success action with `in: query` on action parameter is rejected**
- **Given** an Arazzo 1.1.0 document (`arazzo.yaml`) containing workflow index 0 with a `goto` action at step index 0, action index 0 under `onSuccess`, whose `parameters` list contains entry index 0 `{ name: "x", in: "query", value: "1" }`; `ActionParameterInForbiddenRule` is registered in `RuleSet::default()`; no `--disable-rule` flag is passed; the document parses without syntax errors.
- **When** `php bin/console arazzo validate arazzo.yaml` invokes `Validator::validate()` against the parsed `ArazzoDocument`.
- **Then** validation terminates with a non-zero exit code; `ErrorCollector` reports exactly one error with code `action.parameter_in_forbidden` at pointer `/workflows/0/steps/0/onSuccess/0/parameters/0/in`; the error message states `The 'in' field MUST NOT be used on action parameters`; the error is recorded in the `errors` collection (not `warnings`); no execution side effects occur.

**Scenario 2: Failure action with `in: header` on action parameter is rejected**
- **Given** an Arazzo 1.1.0 document containing workflow index 0 with a `retry` action at step index 1, action index 0 under `onFailure`, whose `parameters` list contains entry index 0 `{ name: "y", in: "header", value: "2" }`; `ActionParameterInForbiddenRule` is active in `RuleSet::activeRules()`.
- **When** `Validator::validate()` iterates all active rules against the parsed `ArazzoDocument`.
- **Then** validation fails with error code `action.parameter_in_forbidden` at pointer `/workflows/0/steps/1/onFailure/0/parameters/0/in`; `ErrorCollector` records the error under `errors` (not `warnings`); `ValidationResult::isValid()` returns `false`.

**Scenario 3: Reusable parameter reference without `in` is accepted**
- **Given** an Arazzo 1.1.0 document containing workflow index 0 with a `goto` action whose `parameters` list contains entry index 0 `{ name: "ref", reference: "$components.parameters.x" }` with no `in` field; `components.parameters` defines key `x`; `ActionParameterInForbiddenRule` is active.
- **When** `Validator::validate()` applies `ActionParameterInForbiddenRule` to the action parameter list.
- **Then** validation passes; no `action.parameter_in_forbidden` error is recorded; the reusable entry (no `in` field) is skipped during the `in`-field scan; `ValidationResult::isValid()` returns `true`.

**Scenario 4: Arazzo 1.0 document declaring action `parameters` is rejected**
- **Given** an Arazzo 1.0.0 document (`$specVersion === SpecVersion::V1_0`) containing workflow index 0 with a success action declaring a `parameters` list `{ name: "x", in: "query", value: "1" }`; the 1.0.x early return in `ActionParameterInForbiddenRule` has been removed.
- **When** `Validator::validate()` applies `ActionParameterInForbiddenRule` to the workflow-level `successActions` parameter list.
- **Then** validation fails with error code `action.parameter_in_forbidden` at pointer `/workflows/0/successActions/0/parameters/0/in`; the error message states `The 'in' field MUST NOT be used on action parameters` and `parameters` is not a valid field for 1.0 action objects; `ValidationResult::isValid()` returns `false`.

**Scenario 5: Valid 1.1 action parameter without `in` is accepted**
- **Given** an Arazzo 1.1.0 document containing workflow index 0 with an action parameter `{ name: "x", value: "1" }` with no `in` field; `ActionParameterInForbiddenRule` is active.
- **When** `Validator::validate()` applies `ActionParameterInForbiddenRule` to the action parameter list.
- **Then** validation passes; no `action.parameter_in_forbidden` error is recorded; only the presence of the `in` field triggers the rule; `ValidationResult::isValid()` returns `true`.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] New validator rule `ActionParameterInForbiddenRule` with stable code `action.parameter_in_forbidden`
- [ ] Rule covers `onSuccess` and `onFailure`, workflow-level and step-level action lists, `goto` and `retry` actions
- [ ] Reusable parameter entries skipped (no `in` field to check)
- [ ] Arazzo 1.0 action `parameters` rejected (field absent from 1.0 schema)
- [ ] Error pointer format: `/workflows/{i}/steps/{j}/onSuccess|onFailure/{k}/parameters/{m}/in`
- [ ] Rule registered in `RuleSet::default()` and listed in validator rule index/documentation
- [ ] Pest tests: success goto with `in`; failure retry with `in`; reusable parameter accepted; 1.0 action parameters rejected; 1.1 action parameter without `in` accepted
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Error logging via `ErrorCollector` instrumented
- [ ] Documentation/rule index updated with the new error code


### 🧬 10. Source Grounding & Code Location

- `packages/document/src/Parser/Parser.php:671-687` — `parseActionParameters()` requires `workflowId`, then blindly calls `parseParameter()`.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/document/src/Parser/Parser.php#L671-L687"><code>Parser.php:671-687</code></a> @ <code>ea8c55a</code></summary>

```php
protected function parseActionParameters(array $obj, ParseContext $ctx): array
{
    if (!isset($obj['parameters']) || !is_array($obj['parameters'])) {
        return [];
    }

    if (!isset($obj['workflowId'])) {
        throw ParserException::missingField($ctx->push('parameters')->push('workflowId'), 'workflowId');
    }

    $params = [];
    foreach (array_values($obj['parameters']) as $i => $item) {
        $params[] = $this->parseParameter($item, $ctx->push('parameters')->push((string) $i));
    }

    return $params;
}
```

</details>

- `packages/document/src/Parser/Parser.php:441-468` — `parseParameter()` accepts `in` via `ParameterIn::tryFrom()`.
- `packages/document/src/Validator/RuleSet.php:100-160` — the registration point for the new rule.
- Spec: Arazzo 1.1.0 §5.8.5.2 (Success Action Object) and §5.8.5.3 (Failure Action Object), `parameters` row.

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** none; validation becomes stricter for invalid documents.
- **Blast radius:** `packages/document` only. Execution already ignores `in` for action parameters.
- **Severity:** medium — conformance and interoperability, not runtime corruption.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Action parameter `in` is accepted today | T1 | Executed validator run on the artifact above | zero errors |
| `parseActionParameters()` delegates to `parseParameter()` | T1 | `Parser.php:671-687` | direct read |
| `parseParameter()` accepts `in` | T1 | `Parser.php:441-468` | direct read |
| No validator rule inspects action parameters | T1 | Rule list in `RuleSet.php:100-160`; grep for `action.parameters` | direct read |
| The spec forbids `in` on action parameters | T2 | Arazzo 1.1.0 Success/Failure Action Object tables | spec text |
