# 02: Preflight validate workflow outputs shape before prerequisite/step execution

**What to build:**
Before executing any workflow prerequisites or running the first step, the runner must validate that all declared workflow output expressions are syntactically valid and reference known schema context. If an output expression contains syntax errors or references non-existent step identifiers, execution must fail fast with a preflight error without performing any network requests or downstream side effects.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Add `validateOutputsShape()` to `packages/runner/src/Sync/WorkflowEngine.php` / `WorkflowPreflight.php`.
- [ ] Inspect each entry in `workflow.outputs` using `ExpressionParser::parse()` to ensure it parses to a valid AST.
- [ ] Validate that expression targets point to declared steps or valid workflow context variables.
- [ ] Throw `PreflightFailureException` with actionable diagnostics when an output expression is malformed.
- [ ] Verify that when preflight fails on outputs, no HTTP or protocol transport call is ever initiated.
- [ ] Add Pest unit and feature tests validating preflight output rejection.
