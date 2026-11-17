# ADR-003: Runner semantics — retry, goto, criteria, workflowId steps

## Status

Accepted

## Date

2026-11-17

## Issue

#382 — `fix(runner): retry, goto, criteria and workflow step semantics`

## Context

Conformance execution cases demand specific runtime semantics verified per case:

- retry budget accounting (`retryLimit: 2` = 3 total requests)
- `onSuccess` end/goto skipping steps
- `onFailure` goto running the recovery step
- `successCriteria` (simple, regex, body comparison) failing the workflow
- `workflowId` steps executing the child and exposing outputs

## Decision

Implement runner semantics in the execution seam:

```
RunnerFacade → WorkflowExecutor::execute() loop → WorkflowEngine transitions → EvaluationEngine criteria → SubWorkflowStepExecutor
```

### Retry

`onFailure.retry.retryLimit: N` means N+1 total requests (1 initial + N retries). `retryAfter: 0` honoured. Retry criteria evaluated per attempt. On limit exhausted, exactly N+1 requests sent and command returns FAILURE.

### onSuccess

`onSuccess: end` — nothing runs after the succeeding step. `onSuccess.goto.stepId` — execution transfers to the goto target, skipping intermediate steps.

### onFailure

`onFailure.goto.stepId` — the recovery step executes; its requests are recorded; outcome not judged by the parent's failure.

### Success criteria

`EvaluationEngine` evaluates `successCriteria`: regex match, body comparison, simple comparison. Criterion failure fails the workflow; nothing runs after unless `onFailure` handles it.

### workflowId steps

`SubWorkflowStepExecutor` runs the child workflow; its outputs are exposed as `$steps.<childStepId>.outputs` to parent steps.

### Failure short-circuit

Nothing runs after a failed step unless `onFailure` goto/retry handles it.

### Coupling

Balanced. Integration strength is medium (model coupling to evaluation engine and corpus runners), distance is low (same repo); XOR = modular. Volatility is low-to-medium.

## Deepening opportunity (flagged, not yet implemented)

Shared `StepOutcomeRouter` for sync/async step-execution loops — retry, goto, criteria, and child-invocation logic currently lives in the sync loop; the async/queue loop duplicates it. Extract one router behind a small interface. Defer unless a second consumer (async loop) needs the same logic.

## Consequences

- Spec-legal workflows that previously failed or ran with partial semantics now execute correctly.
- No signature BC — `RunnerFacade` results and `workflowCallStack` semantics unchanged.
- One Pest scenario per conformance case (S1–S8).

## Location note

Created under `docs/adr/` per the repository's ADR directory convention.
