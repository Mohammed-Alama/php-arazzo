# 04: Implement retry action repair reference execution with budget accounting

**What to build:**
When a failing step triggers a `retry` action that specifies a `stepId` or `workflowId`, the runner must execute that referenced step or workflow as an auxiliary repair procedure *before* re-attempting the original failing step. After the repair reference successfully completes, control returns to re-attempt the original step with fresh context (e.g. newly refreshed authorization tokens). Each execution step within the repair procedure must deduct from the shared workflow step budget.

**Blocked by:** #79

**Status:** ready-for-agent

- [ ] Update `WorkflowEngine::transition()` and `StepOutcomeHandler` to recognize when a `RetryAction` specifies a repair `stepId` or `workflowId`.
- [ ] If a repair reference is specified, dispatch execution to that step/workflow before scheduling or performing the retry attempt.
- [ ] Ensure that step and workflow outputs produced during the repair run update the execution context available to the retried step attempt.
- [ ] Account for repair executions against the shared `stepBudget`; if budget limits are reached during repair, fail with `step-budget-exhausted`.
- [ ] Re-attempt the original step once the repair reference succeeds. If the repair reference itself fails, invoke failure handling on that failure.
- [ ] Add tests matching toolkit's `repairFeedsSubWorkflowStep` scenario (initial failure -> repair run succeeds with fresh token -> retried step succeeds).
