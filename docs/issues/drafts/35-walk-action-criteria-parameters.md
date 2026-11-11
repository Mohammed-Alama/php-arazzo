# Draft issue 35 — Walk action criteria and parameters for unresolved expressions

- **Suggested title:** `[Bug] `ExpressionWalker` does not inspect action criteria or action parameters, so unresolved expressions in actions pass validation`
- **Kind:** `kind:bugfix`
- **Level:** `level:story` (suggested parent: #118 Document & runner validation hardening)
- **Packages:** `document`, `expression`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/document` `ExpressionWalker` (walks workflow params/outputs and step params/body/criteria/outputs only); `ExpressionUnresolvedStepRefRule`/`ExpressionUnresolvedWorkflowRefRule`/`ExpressionUnresolvedInputRefRule` consume its sites.
- **Arazzo spec version: 1.0.x and 1.1.x (action criteria/parameters are Action Object fields in 1.1; expressions appear in `condition`, `value`, selector expressions).
- **PHP target: 8.4.
- **Ecosystem: framework-agnostic core.
- **Kind / level: kind:bugfix, level:story, under #118 (Document & runner validation hardening).
- **Priority: P2 — `$steps.unknown.outputs.x` or `$inputs.missing` referenced inside an action's criteria or parameters passes validation but fails at evaluation time.

### 📝 2. Problem Statement & Intent

`ExpressionWalker::walk()` visits:
- workflow parameters and outputs,
- step parameters, request body, successCriteria, outputs.

It does **not** visit:
- action criteria (`StepFlow::onSuccess`/`onFailure` actions, workflow-level `successActions`/`failureActions`),
- action parameters (`SuccessGotoAction::$parameters`, etc.).

As a result, unresolved step/input/workflow references inside action criteria or parameters are invisible to `ExpressionUnresolvedStepRefRule`, `ExpressionUnresolvedWorkflowRefRule`, and `ExpressionUnresolvedInputRefRule`. Probes confirmed:
- step `onSuccess` criteria referencing `$steps.unknown.outputs.x` → `valid=true`.
- step `onSuccess` goto parameters referencing `$inputs.missing` → `valid=true`.
- workflow-level action criteria referencing unknown steps → `valid=true`.

Expected: `ExpressionWalker` yields `ExpressionSite`s for action criteria conditions and action parameter values, so the unresolved-reference rules flag them with precise paths (e.g. `/workflows/0/steps/0/onSuccess/0/criteria/0/condition`).

### 📦 3. System Dependency Matrix

- **Upstream: `StepFlow` holds `onSuccess`/`onFailure` action lists; workflow holds `successActions`/`failureActions`; action DTOs hold `criteria` (`list<SuccessCriterion>`) and `parameters` (`list<Parameter|Reusable>`).
- **Downstream: `ExpressionUnresolvedStepRefRule`, `ExpressionUnresolvedWorkflowRefRule`, `ExpressionUnresolvedInputRefRule` consume `ExpressionWalker` sites; once walked, they already report unresolved references.
- **Internal seams used: `ExpressionSite` carries `workflow`, `pointer`, `expression`, `referenceKind`; `ExpressionWalker::walkStep()` already yields step-level sites; the action walk mirrors it.
- **Packages touched: `packages/document` (`ExpressionWalker`), `packages/expression` (read-only: rules consume sites; `ExpressionEvaluator` already resolves actions at runtime).
- **Scope boundary: criterion `condition` strings and parameter `value` expressions inside step-level and workflow-level actions. Selector objects within actions are a related but separate concern (the walker handles `Expression`/`Selector` unions elsewhere).

### 📄 4. Structural Artifacts & Raw Inputs

Minimal documents that reproduce the defect:

```yaml
arazzo: 1.1.0
info: { title: Repro, version: "1" }
sourceDescriptions:
  - name: api, url: https://example.com/openapi.yaml, type: openapi
workflows:
  - workflowId: wf
    steps:
      - stepId: one
        operationId: $sourceDescriptions.api.getThing
        onSuccess:
          - name: go
            type: end
            criteria:
              - condition: $steps.unknown.outputs.x == 1
```

and

```yaml
        onSuccess:
          - name: go
            type: goto
            workflowId: child
            parameters:
              - { name: x, value: $inputs.missing }
```

Both validate `valid=true` today.

Expected: validation reports an error for the unresolved reference with a precise pointer into the action's criteria/parameters.

### 🌀 5. Runtime Execution State Context

- Validation phase: `ExpressionWalker::walk()` skips actions entirely; unresolved-reference rules never see action sites.
- Execution phase (not reached because no error): `EvaluationEngine` evaluates action criteria/parameters at runtime and the unresolved expression returns null or fails, producing an obscure runtime error.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `ExpressionWalker::walkStep()` (or a new `walkActions()`) yields `ExpressionSite`s for each action's `criteria[].condition` and `parameters[].value` (when the value is an `Expression`).
- [ ] Both step-level `onSuccess`/`onFailure` actions and workflow-level `successActions`/`failureActions` are walked.
- [ ] `ExpressionSite` pointer targets the exact location (e.g. `/workflows/0/steps/0/onSuccess/0/criteria/0/condition`).
- [ ] `referenceKind` is set appropriately (step ref, workflow ref, input ref) so the unresolved-reference rules apply their existing logic.
- [ ] `ExpressionUnresolvedStepRefRule`/`ExpressionUnresolvedWorkflowRefRule`/`ExpressionUnresolvedInputRefRule` report errors for references discovered in actions.
- [ ] `ExpressionWalkerTest` gains cases: action criteria with unresolved step ref → site yielded; action parameter with unresolved input → site yielded; no false positives for resolvable references.
- [ ] `ValidatorTest`/probe: previously-`valid=true` documents above now invalid with clear errors.
- [ ] PHPStan max and Pint pass.

### 🧬 7. Source Grounding & Code Location

- `packages/document/src/Validator/Support/ExpressionWalker.php:24-45` — `walk()` visits workflow params/outputs and step params/body/criteria/outputs; no action visitation.
- `packages/document/src/Validator/Support/ExpressionWalker.php:60-72` — `walkStep()` yields step-level sites.
- `packages/document/src/Validator/Rules/ActionGotoTargetResolvesRule.php:16-26` — walks actions for target resolution only (not expression references).
- `packages/contracts/src/Spec/Action/SuccessGotoAction.php` — `criteria` + `parameters` fields.
- `packages/contracts/src/Spec/StepFlow.php` — `onSuccess`/`onFailure` lists.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/document/src/Validator/Support/ExpressionWalker.php#L24-L45"><code>ExpressionWalker.php:24-45</code></a> @ <code>ea8c55a</code></summary>

```php
    public function walk(ArazzoDocument $doc, SymbolTable $symbols): iterable
    {
        foreach ($doc->workflows as $wi => $wf) {
            $syms = $symbols->workflows[$wf->workflowId] ?? null;

            yield from $this->walkWorkflowParameters($wf, $wi, $syms);
            yield from $this->walkWorkflowOutputs($wf, $wi, $syms);

            foreach ($wf->steps as $si => $s) {
                yield from $this->walkStep($s, $wi, $si, $syms);
            }
        }
    }
```

</details>

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

- **Public API impact: none (validator is `@internal`); new validation errors may cause previously-valid documents to fail `arazzo validate`.
- **Blast radius: documents with unresolved expressions inside action criteria/parameters (authoring errors); no runtime behaviour change for valid documents.
- **Severity: medium — catches broken action expressions at validation time instead of evaluation time.
- **Rollback: revert the action walk; step/workflow-level expression validation unchanged.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `ExpressionWalker` skips action criteria/parameters | T1 | `ExpressionWalker.php:24-45`; probe-actions.php → all three `valid=true` | executed probe |
| `ExpressionSite` carries pointer + referenceKind | T1 | `ExpressionSite.php` (data class) | direct read |
| Unresolved-reference rules consume walker sites | T1 | `ExpressionUnresolvedStepRefRule` uses `(new ExpressionWalker())->walk()` | direct read |
| Action DTOs hold criteria + parameters | T1 | `SuccessGotoAction.php`, `StepFlow.php` | direct read |
| Workflow-level actions also need walking | T2 | `Workflow` contract has successActions/failureActions; step-level pattern extends | inferred |
