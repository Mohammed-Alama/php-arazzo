# Step Execution Worker (Job Handler) Design

## Overview
The `StepExecutionWorker` is the engine room of the Arazzo framework. It is responsible for taking an `ExecuteStepJob`, safely resolving its parameters against the current state, making the HTTP request, and reporting back to the `Engine` to continue the DAG.

## Core Requirements

1. **Pessimistic Locking & Idempotency**
   - Parallel branches may attempt to update the `WorkflowContext` simultaneously.
   - The worker must acquire a lock (via `LockManagerInterface`) for the specific workflow instance before mutating state.
   - Steps must be idempotent. If a step is retried or a duplicate job is processed, it should check if the step has already been marked complete in the state.

2. **Parameter Resolution**
   - Before executing the HTTP request, all defined Arazzo inputs, parameters, and request bodies must be interpolated.
   - Delegates this task to the `Zero-Code Data Pipelining (JSONPath Resolver)`.

3. **HTTP Execution**
   - Converts the resolved Step DTO into a PSR-7 `RequestInterface`.
   - Executes the request using the `HttpClientInterface` (PSR-18).
   - Captures latency, status code, and headers for the event ledger.

4. **Success/Failure Evaluation**
   - Evaluates Arazzo `successCriteria` (e.g. `statusCode == 200` AND `body.status == "success"`).
   - If success: evaluates `onSuccess` actions (e.g. extracting outputs, ending the workflow).
   - If failure: evaluates `onFailure` actions (e.g. retry strategies, goto, or fail workflow).

5. **State Mutation & Engine Re-entry**
   - Appends the extracted outputs and raw response to the `WorkflowContext`.
   - Invokes the `Engine->evaluate()` method again so the Engine can determine if new steps are now unlocked.

## Architecture

### The Execution Loop (Per Step)

1. **Pop**: Queue driver pops `ExecuteStepJob(stepId, context)`.
2. **Lock**: Acquire lock on `workflow_instance_{id}`.
3. **Idempotency Check**: Is `stepId` already in `context->completedSteps`? If yes, exit.
4. **Resolve**: Use `ExpressionResolver` to compile the PSR-7 Request.
5. **Execute**: Send HTTP request via `HttpClientInterface`.
6. **Evaluate**: Check `successCriteria`.
7. **Extract**: Parse `$outputs` from the response body.
8. **Commit**: Update the Hot State Cache via `StateStoreInterface`.
9. **Dispatch Ledger**: Fire `StepExecuted` event for the Event Sourcing ledger.
10. **Re-evaluate DAG**: Call `Engine->evaluate($workflow, $newContext)`.
11. **Release**: Release the lock.

## Open Questions / Trade-offs
- How do we handle long-polling or rate-limited APIs? *Solution: The `HttpClientInterface` can throw specific `RateLimitException`s, which the worker catches to push the job back onto the queue with a delay (utilizing the `$delaySeconds` in `QueueDriverInterface`).*
