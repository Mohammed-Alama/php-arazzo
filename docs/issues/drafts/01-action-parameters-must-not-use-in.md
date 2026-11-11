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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] A new rule reports a stable, documented error code (suggested: `action.parameter_in_forbidden`) at `/workflows/{i}/steps/{j}/onSuccess|onFailure/{k}/parameters/{m}/in`.
- [ ] The rule covers both `onSuccess` and `onFailure`, both workflow-level and step-level action lists, and both `goto` and `retry` actions.
- [ ] Reusable parameter entries are skipped (they have no `in` field; draft 05 covers their resolution).
- [ ] Arazzo 1.0 documents that declare action `parameters` are rejected (the field does not exist in 1.0).
- [ ] Pest tests cover: success goto with `in`; failure retry with `in`; reusable parameter accepted; 1.0 action parameters rejected; 1.1 action parameter without `in` accepted.
- [ ] The rule is added to `RuleSet::default()` and listed in the validator documentation/rule index.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** none; validation becomes stricter for invalid documents.
- **Blast radius:** `packages/document` only. Execution already ignores `in` for action parameters.
- **Severity:** medium — conformance and interoperability, not runtime corruption.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Action parameter `in` is accepted today | T1 | Executed validator run on the artifact above | zero errors |
| `parseActionParameters()` delegates to `parseParameter()` | T1 | `Parser.php:671-687` | direct read |
| `parseParameter()` accepts `in` | T1 | `Parser.php:441-468` | direct read |
| No validator rule inspects action parameters | T1 | Rule list in `RuleSet.php:100-160`; grep for `action.parameters` | direct read |
| The spec forbids `in` on action parameters | T2 | Arazzo 1.1.0 Success/Failure Action Object tables | spec text |
