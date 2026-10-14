# 01: Reject ambiguous goto and retry actions declaring both stepId and workflowId

**What to build:**
When an Arazzo document declares an action that specifies both `stepId` and `workflowId` on a single `goto` or `retry` action, both the document validator and the runtime transition engine must immediately reject the document with a typed `ambiguous-target` error. Per the Arazzo specification, `stepId` and `workflowId` are mutually exclusive target specifiers on control flow actions.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Add `ActionTargetExclusivityRule` to `packages/document/src/Validator/Rules/` and register in `RuleSet.php`.
- [ ] Ensure validation fails with a distinct error message and code when an action specifies both `stepId` and `workflowId`.
- [ ] Add runtime defense in `packages/runner/src/Sync/WorkflowEngine.php` throwing `ExecutionError` with code `ambiguous-target` if a dual-targeted action is evaluated.
- [ ] Verify both `goto` (Success/Failure) and `retry` actions are validated identically.
- [ ] Add Pest unit tests in `packages/document/tests/` and `packages/runner/tests/` verifying valid single-target actions pass and dual-targeted actions fail.
