# Design Specification: Circuit Breaker (`maxSteps`)

## 1. Overview
The Arazzo workflow specification supports complex control flows like `goto` and `retry`. These features introduce the risk of infinite loops (e.g., a `goto` that points to a previous step without a terminating condition, or aggressive retry limits). 

To prevent runaway workflows from consuming infinite resources or crashing workers, we are introducing a global `maxSteps` circuit breaker. This budget limits the total number of step executions allowed per workflow run across all synchronous and asynchronous boundaries.

## 2. Configuration
The circuit breaker will be configured at the executor level, allowing developers to set standard limits for their environment.

- **`WorkflowExecutor`** (Synchronous): Add `private int $maxSteps = 1000` to the constructor.
- **`StepExecutionWorker`** (Asynchronous): Add `private int $maxSteps = 1000` to the constructor.

## 3. State Tracking
Because asynchronous workflows may suspend and resume across different PHP processes, the step execution count must be tracked within the workflow's state payload.

- Modify `Alama\Arazzo\Runner\WorkflowContext`:
  - Add property: `private int $stepsExecuted = 0`.
  - Add getter: `public function getStepsExecuted(): int`.
  - Add immutable mutator: `public function withIncrementedExecutionCount(): self`. This creates a new instance with `$stepsExecuted + 1`.
  - Update `WorkflowContext::__construct` and all existing `with*` methods to carry the `$stepsExecuted` value forward.
- Modify `StepExecutionWorker::serialize()` and `reconcileWithPersistedState()`:
  - Ensure the `stepsExecuted` integer is saved to and loaded from the `StateStoreInterface`.

## 4. Enforcement
The budget check occurs immediately before a step's logic is executed. 

**Guard Clause Logic:**
```php
if ($context->getStepsExecuted() >= $this->maxSteps) {
    throw new StepBudgetExceededException("Workflow exceeded the maximum step budget of {$this->maxSteps}.");
}
$context = $context->withIncrementedExecutionCount();
```

**Injection Points:**
- `StepExecutionWorker::handle()`: Inside the lock closure, before the `$executor->execute(...)` call.
- `WorkflowExecutor::execute()`: Inside the main loop, before the `$this->stepExecutor->execute(...)` call.

## 5. Error Handling
Create a specific exception class to differentiate budget exhaustion from standard step failures.

- **Class:** `Alama\Arazzo\Runner\Exceptions\StepBudgetExceededException`
- **Extends:** `\RuntimeException`

This allows consuming frameworks (like Laravel) to catch this specific exception and report it as a structural workflow error rather than a transient API failure.
