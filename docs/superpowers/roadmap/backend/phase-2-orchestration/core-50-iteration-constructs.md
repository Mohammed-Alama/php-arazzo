# Iteration Constructs

Category: **core** · Phase: **2-orchestration** · Tier: **OSS**
Depends on: shipped `Native Asynchronous Control Flow`

## Problem

There is a paradigm mismatch between code-first workflow engines and declarative specifications like Arazzo when it comes to loops. `durable-workflow` leverages native PHP `while` loops to seamlessly repeat activities (e.g., polling an endpoint until a status changes, or fetching all pages of a paginated API). 

Arazzo is fundamentally a directed graph. While it supports failure-based retries via `onFailure.retry`, and jumping via `goto`, it lacks a native, declarative iteration construct for business logic (i.e. repeating a step that succeeded HTTP-wise but whose payload indicates more work is needed). Modeling loops with `goto` requires manually maintaining iteration counters in the context and results in unstructured control flow that is hard to observe and aggregate.

## Feature

Introduce step-level `x-loop` (or `x-until`) extensions to handle iterative processing declaratively, bringing loop parity with code-first engines without losing graph observability.

```yaml
steps:
  - stepId: pollForCompletion
    operationId: getStatus
    x-loop:
      # Evaluated after each execution. If true, the loop breaks.
      until: $response.body.status == 'completed'
      # Safety bound to prevent infinite loops (DLQ/fail if hit)
      maxIterations: 10
      # Delay between iterations (integrates with exec-48 durable timers)
      delay: PT5M
```

```yaml
steps:
  - stepId: fetchAllPages
    operationId: getRecords
    parameters:
      - name: cursor
        in: query
        # Reference the loop's internal iteration state
        value: $steps.fetchAllPages.loop.next_cursor
    x-loop:
      until: $response.body.meta.next_cursor == null
      maxIterations: 50
      # Durably extract and update the cursor for the next iteration
      updateState:
        next_cursor: $response.body.meta.next_cursor
      # Durably aggregate results across iterations
      aggregate:
        - target: all_records
          source: $response.body.data
```

- **Ledger Integration**: Emits `LoopIterationStarted` and `LoopIterationCompleted` events to the `EventLedgerInterface`. Replay correctly fast-forwards through previously completed iterations.
- **State Aggregation**: Introduces a mechanism to append or merge data across iterations (e.g., aggregating pages of results into a single array).
- **Idempotency**: Each iteration receives a unique determinism seed/idempotency key so that a crash during iteration 4 doesn't replay iterations 1-3.

## Acceptance

- A step with `x-loop` executes repeatedly until the `until` expression evaluates to `true` or `maxIterations` is breached (which throws a defined workflow exception).
- Variables extracted in `updateState` correctly carry over to the next iteration's parameter resolution.
- Aggregated variables in `aggregate` correctly accumulate arrays across all iterations and are available to subsequent steps.
- The Event Ledger maintains a clean, linear history of iterations that can be replayed deterministically across worker restarts.
- `delay` uses the queue-native delayed dispatch mechanism (synergizing with `exec-48-durable-timers`) rather than busy-waiting.

## Out of scope

- Parallel `foreach` execution over a static array (Fan-out / Fan-in). This is explicitly covered by `exec-08-fan-out-in`. This stub is strictly for sequential iterative control flow.
- Infinite loops. A `maxIterations` boundary is strictly enforced at the engine level to prevent runaway queue consumption.
