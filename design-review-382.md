# Design Review — Issue #382 (Stage 3)

Status: proposed, awaiting sign-off. No source code changed.

## 1. Inputs confirmed

- Issue #382: `fix(runner): retry, goto, criteria and workflow step semantics` — kind:bugfix, level:story, package:runner, parent #378, blocked-by #381, blocking #390. Open, 0 comments.
- Acceptance cases S1–S8 map to conformance exec-* cases (exec-failing-criteria, exec-on-failure-retry, exec-retry-limit-exhausted, exec-onSuccess-end, exec-onSuccess-goto, exec-onFailure-goto, exec-successCriteria-regex, exec-successCriteria-body, exec-workflowId-step).
- Source grounding pinned: RunnerFacade.php:1-80 (commit-pinned permalink), EvaluationEngine.php:1-40 (commit-pinned), verified 2026-11-16.

## 2. Domain-modeling lens (sharpened terms)

| Term | Sharpened meaning | Canonical location |
|------|-------------------|--------------------|
| retry budget | `retryLimit: N` permits N+1 total requests (1 initial + N retries). Exhaustion = attemptsSoFar >= effectiveLimit in `RetryPolicy::isExhausted`. | `WorkflowEngine::transition` (RetryAction) + `RetryPolicy` (`packages/runtime/src/Policy/RetryPolicy.php`) |
| onSuccess end | Terminal success transition; workflow completes, nothing after runs. | `WorkflowEngine::transition` (SuccessEndAction) |
| onSuccess goto | Transfer to target step/workflow; intermediate steps skipped. | `WorkflowEngine::transition` (SuccessGotoAction) |
| onFailure goto | Recovery step executes on failure; its requests recorded, outcome not judged by parent. | `WorkflowEngine::transition` (FailureGotoAction) |
| successCriteria | regex / body comparison / simple / jsonpath / xpath evaluated against step response root; short-circuits at first non-passed. | `EvaluationEngine::evaluateSuccessCriteria` → `CriteriaEvaluator` → `BuiltInCriterionEvaluator` |
| workflowId step | Child workflow executes in its own context via `SubWorkflowStepExecutor`; child outputs exposed as `$steps.<stepId>.outputs`. | `ExecutionGraphFactory` registers `SubWorkflowStepExecutor` (plugin priority 0, before HTTP) |
| failure short-circuit | Failed step ends workflow as failed unless onFailure retry/goto handles it. | `WorkflowExecutor::executeCanonically` + `WorkflowEngine::transition` |

Hard-to-reverse decision to record (no ADR needed — all three ADR criteria fail): retry budget accounting stays in `WorkflowEngine::transition` (already there, `RetryPolicy` already exists); no new domain concept introduced. No `CONTEXT.md`/`docs/adr` exist yet; none created.

## 3. Codebase-design lens — seam definition

Existing seams (wired from #334):
- `RunnerFacade` accepts executor/extractor/validator seams via constructor (`OpenApiExecutorInterface`, `OutputExtractorInterface`, `ResponseValidatorInterface`) + `EvaluationEngineInterface`.
- `ExecutionGraphFactory` builds the graph: `StepExecutor` + `WorkflowEngine` + `WorkflowExecutor`; registers `SubWorkflowStepExecutor`.
- `WorkflowExecutor::execute()` → `executeOnce()` → `executeCanonically()` = the step execution loop.
- `WorkflowEngine::transition(document, workflow, step, state, criteriaMet)` decides retry/goto/end/invoke/next.
- `StepExecutor::execute(step, context, document)` runs HTTP (or plugin) and evaluates success criteria.
- `EvaluationEngine::evaluateSuccessCriteria(step, context, document)` → bool.
- `SubWorkflowStepExecutor` runs child workflow via `$this->executor->execute($target, ...)`, returns outputs as step outcome.

Where each semantic lives (seam definition):

| Semantic | Lives in | Seam / method |
|----------|----------|---------------|
| retry budget accounting | `WorkflowEngine::transition` (RetryAction branch) | `attemptFor($stepId)` vs `RetryPolicy::isExhausted(attemptsSoFar, retryLimit)`; retryAfter delay via `RetryPolicy::calculateDelay` |
| goto target resolution | `WorkflowEngine::transition` (GotoAction branches) + `WorkflowEngine::target()` + `findWorkflow()`/`assertCanEnter()` | `target()` validates stepId exists; `assertCanEnter()` guards cycle/depth |
| success criteria (regex/body) | `EvaluationEngine` → `CriteriaEvaluator` → `BuiltInCriterionEvaluator::evaluateRegex`/`evaluateJsonPath` | `evaluateCriteria()` short-circuits at first non-passed |
| child-workflow invocation (workflowId) | `SubWorkflowStepExecutor::execute` | wraps `WorkflowExecutor::execute($target, $document, $bound, $child)`; outputs surfaced as step outputs |
| step execution loop | `WorkflowExecutor::executeCanonically()` | while loop over stepId; dispatch StepStarted/StepRetried/StepFailed/StepExecuted/Run events |
| runner results & call stack | `RunnerFacade` + `ExecutionResult` / `workflowCallStack` | unchanged per #341 design |

Interface contract (no code change — descriptive only):

```
RunnerFacade::run(execute, extract, validate) : array  # facade seam (unchanged)
WorkflowExecutor::execute(Workflow, ArazzoDocument, inputs) : ExecutionResult  # step loop seam
WorkflowEngine::transition(document, workflow, step, state, criteriaMet) : Transition  # decision seam
EvaluationEngine::evaluateSuccessCriteria(step, context, document) : bool  # criteria seam
StepExecutor::execute(step, context, document) : [WorkflowContext, bool]  # HTTP step seam
SubWorkflowStepExecutor::execute(step, context, document, executionId) : StepExecutionOutcome  # child invocation seam
```

Tests: Pest, one scenario per conformance case S1–S8, in `packages/runner/tests/Sync/` (e.g. `WorkflowExecutorConformanceTest.php`), each a single scenario asserting requests count / exit status / step skips / outputs exposure.

## 4. Balanced-coupling lens

Dimensions scored at the module level of abstraction (runner package is the highest distance in this context):

| Integration | Strength | Distance | Volatility | Balance verdict |
|-------------|----------|----------|------------|-----------------|
| WorkflowExecutor → EvaluationEngine | Contract (engine interface, `evaluateSuccessCriteria`) | Low (same package) | Medium | **Balanced** (low strength XOR low distance) |
| WorkflowExecutor → OpenApiExecutorInterface | Contract | Low (same package, injected) | Low (PSR-18 stable) | **Balanced** |
| WorkflowEngine ↔ StepExecutor | Model + Functional (shared ExecutionState/Transition knowledge) | Low (same package) | High (retry/goto/criteria semantics) | **Balanced** (high strength offset by low distance) |
| WorkflowExecutor → SubWorkflowStepExecutor | Contract (plugin interface) | Low (same package) | Medium | **Balanced** |
| RunnerFacade → sub-graph (WorkflowExecutor) | Contract (facade) | Low (facade wraps executor) | Low | **Balanced** |

Verdict: **balanced**. No unbalanced-and-volatile integrations. No distributed-monolith risk: all collaborators are co-located in `packages/runner` (same package, same team, synchronous in-process calls). Strength is high only where distance is low (WorkflowEngine↔StepExecutor share ExecutionState), which the balance rule permits. Retry/goto/criteria/workflowId semantics are localized to `WorkflowEngine::transition` and `EvaluationEngine`, so change cascades are contained — locality holds.

## 5. Deepening opportunity (review lens substitute)

The sync `WorkflowExecutor::executeCanonically()` and the async `StepOutcomeHandler`/`RunControlFlow` both drive the step loop and both depend on `WorkflowEngine::transition`, but the async path re-implements outcome routing (retry/goto/end/invoke) in `StepOutcomeHandler`. This is a shallow duplication seam: two adapters own the loop while the decision stays in `WorkflowEngine`. Deepening opportunity: extract a small `StepOutcomeRouter` (or similar) module behind a tiny interface so sync and async loops share one routing decision, removing duplicated transition knowledge. Flagged *before* implementation — do not implement in Stage 3.

## 6. Sign-off

Design review posted as this comment. Awaiting explicit human sign-off on issue #382 before Stage 4 begins. Coupling classified balanced; no unaddressed deepening opportunity beyond the flagged router; all four skills invoked (domain-modeling, codebase-design, balanced-coupling, review as improve-codebase-architecture substitute).
