# Draft issue 34 — Validate workflow-level success/failure action goto targets

- **Suggested title:** `[Bug] Workflow-level `successActions`/`failureActions` goto targets are not validated`
- **Kind:** `kind:bugfix`
- **Level:** `level:story` (suggested parent: #118 Document & runner validation hardening)
- **Packages:** `document`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/document` `ActionGotoTargetResolvesRule` (validates step-level onSuccess/onFailure only); workflow `successActions`/`failureActions` parsed but not validated for target resolution.
- **Arazzo spec version:** 1.0.x and 1.1.x (workflow-level actions are workflow Object fields; §5.8.4 `successActions`/`failureActions`, §5.8.7 Success Action Object, §5.8.8 Failure Action Object).
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core.
- **Kind / level:** `kind:bugfix`, `level:story`, under #118 (Document & runner validation hardening).
- **Priority:** P2 — a workflow-level `goto` to an unknown `stepId`/`workflowId` passes validation but fails at execution with an obscure error.

### 📝 2. Problem Statement & Intent

A workflow can declare `successActions`/`failureActions` that apply to all steps (§5.8.4). A `goto` action names a `stepId` (within the current workflow) or a `workflowId`. php-arazzo validates step-level action targets (`ActionGotoTargetResolvesRule` checks step `onSuccess`/`onFailure`) but does not validate workflow-level action targets:

- `ActionGotoTargetResolvesRule::check()` iterates `$doc->workflows` → `$w->steps` → `$s->flow->onSuccess`/`onFailure` only; it never touches `$w->successActions`/`$w->failureActions`.
- Probe confirmed: a workflow-level `successActions` with `type: goto, stepId: nope` validates `valid=true`.
- At execution, an unresolved goto target fails at runtime with an obscure error instead of a clear validation error.

Expected: `ActionGotoTargetResolvesRule` (or a companion rule) also validates that workflow-level `successActions`/`failureActions` goto actions resolve to a declared `stepId` in the same workflow or a declared `workflowId` in the document.

### 📦 3. System Dependency Matrix

- **Upstream:** workflow-level actions parsed by `Parser::parseWorkflow()` (`successActions`/`failureActions` lists); `Action` DTOs (`SuccessGotoAction`, `FailureGotoAction`, `RetryAction`) carry `stepId`/`workflowId`/`criteria`.
- **Downstream: `StepExecutor`/`ActionGotoTargetResolvesRule` runtime resolution — the runner resolves goto targets at execution; validation should catch mistakes earlier.
- **Internal seams used:** `ActionGotoTargetResolvesRule` already has the step-level logic; the same logic applies to workflow-level lists with path `/workflows/{i}/successActions/{j}`.
- **Packages touched: `packages/document` (primary).
- **Scope boundary: goto target resolution only (stepId/workflowId existence). `criteria` within workflow-level actions are expression-validation concerns (draft 35 covers action criteria/parameters walking). Retry targets are also actions but `ActionRetryLimitsRule` already handles retry limits; goto target existence is the gap.

### 📄 4. Structural Artifacts & Raw Inputs

Minimal document that reproduces the defect:

```yaml
arazzo: 1.1.0
info: { title: Repro, version: "1" }
sourceDescriptions:
  - name: api, url: https://example.com/openapi.yaml, type: openapi
workflows:
  - workflowId: wf
    successActions:
      - name: go
        type: goto
        stepId: nope   # does not exist in this workflow
    steps:
      - stepId: one
        operationId: $sourceDescriptions.api.getThing
```

Validation reports `valid=true` even though `stepId: nope` is undeclared.

Expected: validation fails with an error such as "Workflow-level success action 'go' goto stepId 'nope' is not declared in workflow 'wf'" at path `/workflows/0/successActions/0/stepId`.

### 🌀 5. Runtime Execution State Context

- Validation phase: `Validator::validate()` → `ActionGotoTargetResolvesRule::check()` skips `$w->successActions`/`$w->failureActions`.
- Execution phase (not reached because no error): the runner would resolve the goto target and fail with `GotoTargetNotFoundException` or similar.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `ActionGotoTargetResolvesRule` (or new rule) validates workflow-level `successActions`: each `goto` action's `stepId` exists in the same workflow's steps, or its `workflowId` exists in the document.
- [ ] Same validation for workflow-level `failureActions` (goto and retry target resolution).
- [ ] Error paths point at `/workflows/{i}/successActions/{j}/stepId` or `/workflowId`.
- [ ] `end`-type actions (no target) pass unchanged.
- [ ] `retry`-type failure actions with `stepId`/`workflowId` are validated for target existence.
- [ ] Step-level validation (existing) is unchanged.
- [ ] `ValidatorTest` gains cases: workflow-level goto to unknown stepId → invalid; goto to known stepId → valid; goto to known workflowId → valid; failureActions retry to unknown workflowId → invalid.
- [ ] PHPStan max and Pint pass.

### 🧬 7. Source Grounding & Code Location

- `packages/document/src/Validator/Rules/ActionGotoTargetResolvesRule.php:16-26` — validates step-level onSuccess/onFailure only; skips workflow-level actions.
- `packages/document/src/Parser/Parser.php` — parses workflow-level `successActions`/`failureActions`.
- `packages/contracts/src/Spec/Action/SuccessGotoAction.php` — `stepId`/`workflowId` fields.
- `packages/contracts/src/Spec/Action/FailureGotoAction.php` — same.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/document/src/Validator/Rules/ActionGotoTargetResolvesRule.php#L16-L26"><code>ActionGotoTargetResolvesRule.php:16-26</code></a> @ <code>ea8c55a</code></summary>

```php
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wi => $w) {
            foreach ($w->steps as $si => $s) {
                $this->checkList($s->flow->onSuccess, $syms, $symbols, $errors, "/workflows/{$wi}/steps/{$si}/onSuccess");
                $this->checkList($s->flow->onFailure, $syms, $symbols, $errors, "/workflows/{$wi}/steps/{$si}/onFailure");
            }
        }
    }
```

</details>

### 🌍 8. Public Domains & Impact Profile

- **Public API impact: none (validator is `@internal`); new validation error may cause previously-valid documents to fail `arazzo validate`.
- **Blast radius: documents with workflow-level goto to undeclared targets (authoring errors); no runtime behaviour change for valid documents.
- **Severity: medium — catches misconfigured workflow actions at validation time instead of runtime.
- **Rollback: remove the workflow-level branch; step-level validation unchanged.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `ActionGotoTargetResolvesRule` checks step-level only | T1 | `ActionGotoTargetResolvesRule.php:16-26` | direct read |
| Workflow-level successActions/failureActions parsed but not validated for targets | T1 | probe-actions.php → `valid=true` for `stepId: nope` | executed probe |
| Workflow contract has successActions/failureActions fields | T1 | `Workflow.php` constructor | direct read |
| Runtime would fail on unresolved goto target | T2 | `GotoTargetNotFoundException` exists; runner resolves goto at execution | inferred from exception class |
