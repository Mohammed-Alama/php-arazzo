# Draft issue 26 — Execute workflow-level `dependsOn` before running a workflow

- **Suggested title:** `[Bug] Workflow-level `dependsOn` is validated but never enforced at execution time`
- **Kind:** `kind:bugfix`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `runner`, `document`, `cli`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/runner` `WorkflowExecutor::executeCanonically()`; `packages/document` `Workflow` contract and parser; `packages/cli` `RunnerFacade::run()`/`execute()`.
- **Arazzo spec version:** 1.0.x and 1.1.0 (workflow `dependsOn` is a Workflow Object field in all supported versions).
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core + CLI.
- **Kind / level:** `kind:bugfix`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P0 — a workflow declared to depend on another will run even if its dependency has not completed or has failed.

### 📝 2. Problem Statement & Intent

A workflow can declare `dependsOn` listing sibling workflows that MUST complete before it is processed (§5.8.4 Workflow Object). php-arazzo parses the field (`Parser.php:153-163`) and validates it (`WorkflowDependsOnExistsRule`, `WorkflowDependsOnNoCycleRule`), but the runner never consults it:

- `grep -rn -- "dependsOn" packages/runner/src` → no matches.
- `WorkflowExecutor::executeCanonically()` (`WorkflowExecutor.php:99-180`) runs `firstStep()` then a strict sequential loop, with no reference to `$workflow->dependsOn`.
- `RunnerFacade::execute()` (`RunnerFacade.php:56-72`) executes only the single selected workflow, so even a topological order across workflows is impossible from the CLI.

The consequence: `workflow B dependsOn: [A]` will execute B's steps regardless of whether A ran, whether A succeeded, or whether A's outputs are available. `$workflows.A.outputs.*` expressions in B resolve to `null` because `execute()` never populates dependency outputs into `WorkflowContext::getWorkflows()` (`WorkflowContext.php:28`).

Expected: when executing a workflow, the runner must first run every unresolved `dependsOn` dependency (respecting transitive deps and cycles already rejected by validation), record each dependency's outputs under `$workflows.<depId>.outputs.*`, and only then execute the selected workflow — failing the run if any dependency failed.

### 📦 3. System Dependency Matrix

- **Upstream:** Arazzo spec §5.8.4 `dependsOn`; `WorkflowDependsOnExistsRule` / `WorkflowDependsOnNoCycleRule` already guarantee targets exist and are acyclic.
- **Downstream:** `RunnerFacade` single-workflow entry point; CLI `--workflow` selection; `$workflows.<id>.outputs.*` resolution in `ExpressionEvaluator.php:84-91` (implemented but unpopulated for deps).
- **Internal seams used:** `WorkflowContext::getWorkflows()` / `withWorkflowData()` (`WorkflowContext.php:214`); `ExecutionState::workflowCallStack`; `SubWorkflowStepExecutor` already demonstrates child-workflow output recording.
- **Packages touched:** `packages/runner` (primary), `packages/document` (read-only confirmation), `packages/cli` (read-only confirmation).
- **Scope boundary:** local workflow ids only. Cross-document `$sourceDescriptions.*` deps are out of scope (#83 covers cross-document `$sourceDescriptions.*` refs); a `dependsOn` entry that cannot be resolved locally should fail the preflight.

### 📄 4. Structural Artifacts & Raw Inputs

Minimal document that reproduces the defect:

```yaml
arazzo: 1.1.0
info: { title: Repro, version: "1" }
sourceDescriptions:
  - name: api, url: https://example.com/openapi.yaml, type: openapi
workflows:
  - workflowId: base
    steps:
      - stepId: fetch
        operationId: $sourceDescriptions.api.getOrder
        outputs:
          order_id: $response.body#/id
  - workflowId: dependent
    dependsOn: [base]
    steps:
      - stepId: use
        operationId: $sourceDescriptions.api.useOrder
        parameters:
          - { name: order_id, in: header, value: $workflows.base.outputs.order_id }
```

`WorkflowExecutor::execute($document->workflows[1], $document, [])` runs `use` even though `base` never executed; `$workflows.base.outputs.order_id` is `null`.

### 🌀 5. Runtime Execution State Context

- Workflow: `dependent` (the workflow carrying `dependsOn: [base]`).
- Dependency: `base` must execute first; its `order_id` output must be recorded under `$workflows.base.outputs.order_id`.
- Inputs: `[]` (no inputs needed to reproduce).
- State: `WorkflowContext::getWorkflows()` contains only the current workflow's inputs; dependency outputs are never written.
- Events: `RunStartedEvent` fires for `dependent`; no `RunStartedEvent`/`StepStartedEvent` for `base`.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `WorkflowExecutor::execute()` (or a new orchestration layer) resolves `$workflow->dependsOn` and executes each dependency before the selected workflow.
- [ ] Dependency execution is recursive: if `C dependsOn: [B]` and `B dependsOn: [A]`, order is A → B → C.
- [ ] Each dependency's outputs are recorded via `WorkflowContext::withWorkflowData($depId, ['outputs' => ...])` so `$workflows.<depId>.outputs.*` resolves.
- [ ] If any dependency fails (step failure, unmet successCriteria), the selected workflow is not executed and the run reports failure.
- [ ] Cycle and missing-target cases remain handled by `WorkflowDependsOnNoCycleRule` / `WorkflowDependsOnExistsRule` (preflight), not duplicated at runtime.
- [ ] `RunnerFacade::execute()` result includes executed dependency workflows in `workflowCallStack` (or a new field) so callers can observe the dependency chain.
- [ ] `WorkflowExecutorTest` gains a case: dependent workflow with a dependency that returns an output; assert the dependent step sees the dependency output.
- [ ] `WorkflowExecutorTest` gains a case: dependent workflow whose dependency fails; assert the dependent never runs and the run fails.
- [ ] PHPStan max and Pint pass.

### 🧬 7. Source Grounding & Code Location

- `packages/runner/src/Sync/WorkflowExecutor.php:53` — `execute()` entry; `executeCanonically()` at :99–180 runs a strict sequential loop with no `dependsOn`.
- `packages/runner/src/Sync/WorkflowExecutor.php:85` — `withWorkflowData($workflow->workflowId, ['inputs' => $inputs])` records only the current workflow's inputs.
- `packages/runner/src/RunnerFacade.php:56-72` — `execute()` runs only the selected workflow; no dependency orchestration.
- `packages/contracts/src/State/WorkflowContext.php:258-281` — `getWorkflows()` / `withWorkflowData()` seam for recording dependency outputs.
- `packages/document/src/Parser/Parser.php:153-163` — parses workflow `dependsOn` into `list<string>`.
- `packages/document/src/Validator/Rules/WorkflowDependsOnExistsRule.php:12-29` — validates target exists.
- `packages/document/src/Validator/Rules/WorkflowDependsOnNoCycleRule.php` — validates acyclicity.
- `packages/expression/src/ExpressionEvaluator.php:84-91` — resolves `$workflows.<id>.outputs.*` (implemented, unpopulated for deps).

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/runner/src/Sync/WorkflowExecutor.php#L99-L115"><code>WorkflowExecutor.php:99-115</code></a> @ <code>ea8c55a</code></summary>

```php
    private function executeCanonically(Workflow $workflow, ArazzoDocument $document, array $inputs, ?WorkflowContext $context, string $executionId): ExecutionResult
    {
        // Preflight runs before the first side effect (no events, no state).
        if ($this->preflight !== null) {
            ...
        }

        $executionId = ...;
        $context ??= new WorkflowContext($workflow->workflowId, $inputs);
        $context = $context->withWorkflowData($workflow->workflowId, ['inputs' => $inputs]);
        ...
        return $this->executeCanonically(...);
    }
```

</details>

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/runner/src/Sync/WorkflowExecutor.php#L113-L152"><code>WorkflowExecutor.php:113-152</code></a> @ <code>ea8c55a</code></summary>

```php
        $stepId = $this->firstStep($workflow);
        $results = [];
        try {
            while ($stepId !== null) {
                $step = $this->step($currentWorkflow, $stepId);
                ...
                [$stepContext, $success] = $this->stepExecutor->execute(...);
                ...
            }
        }
```

</details>

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** `WorkflowExecutor::execute()` signature unchanged; behaviour changes so dependency workflows run first. `RunnerFacade::execute()` output gains dependency visibility.
- **Blast radius:** `packages/runner` execution path; any code that calls `execute()` on a workflow with `dependsOn`. No persisted state changes.
- **Severity:** high — workflows with declared dependencies execute out of order and read `null` outputs, silently producing wrong results.
- **Rollback:** revert the dependency orchestration; validation rules still reject invalid `dependsOn`.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No `dependsOn` reference in runner/src | T1 | `grep -rn -- "dependsOn" packages/runner/src` → no matches | executed |
| `executeCanonically()` runs a strict sequential loop | T1 | `WorkflowExecutor.php:99-180` | direct read |
| `RunnerFacade::execute()` runs only the selected workflow | T1 | `RunnerFacade.php:56-72` | direct read |
| Parser parses workflow `dependsOn` | T1 | `Parser.php:153-163` | direct read |
| `WorkflowDependsOnExistsRule`/`NoCycleRule` validate but don't execute | T1 | rule files; direct read | direct read |
| `$workflows.<id>.outputs.*` resolution implemented but unpopulated for deps | T1 | `ExpressionEvaluator.php:84-91`; probe-depends.php → `outputs={"inherited":null}` | executed probe |
| Cross-document deps out of scope (#83) | T2 | issue #83 covers cross-document `$sourceDescriptions.*` refs | inferred from issue index |
