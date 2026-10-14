# 03: Support step-level goto to workflowId as one-way transfer

**What to build:**
When a step executes a `goto` action targeting a `workflowId`, it represents a one-way transfer of workflow control per the Arazzo specification. The calling workflow terminates immediately, and execution transfers permanently to the target workflow. Context does not return to the calling workflow upon completion, and the final `ExecutionResult` reflects the target workflow's settled status and outputs.

**Blocked by:** 01: Reject ambiguous goto and retry actions declaring both stepId and workflowId

**Status:** ready-for-agent

- [ ] Distinguish intra-workflow `goto.stepId` jumps from inter-workflow `goto.workflowId` one-way transfers in `packages/runner/src/Sync/WorkflowEngine.php`.
- [ ] In `packages/runner/src/Sync/WorkflowExecutor.php` and queue workers, treat `TransitionType::Goto` targeting a `workflowId` as a terminal transfer.
- [ ] Push a one-way transfer frame to the `workflowCallStack` while marking the originating workflow frame terminated.
- [ ] Ensure the final execution result yields outputs from the target workflow rather than attempting to resolve outputs from the abandoned workflow.
- [ ] Verify that step budget deductions persist across the one-way transfer.
- [ ] Add sync and async test cases verifying one-way transfer semantics.
