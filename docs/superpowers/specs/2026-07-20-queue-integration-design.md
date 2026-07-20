# Queue Integration & Orchestration Design

## Overview
To achieve high concurrency and parallel execution of Arazzo workflows, the core engine must transition from a synchronous loop to an asynchronous, event-driven DAG execution model. This design outlines a framework-agnostic queue dispatching mechanism using decentralized choreography.

## Core Requirements
1. **Framework Agnostic Orchestration:** The core engine must be able to dispatch background jobs without hardcoding Laravel-specific classes (`Bus::dispatch`, `ShouldQueue`).
2. **Decentralized Choreography:** The orchestration should be decentralized. Workers process a step and independently evaluate if downstream steps are unlocked, preventing a central orchestrator bottleneck.
3. **Double-Dispatch Prevention:** In cases of scatter-gather execution (e.g., Step C waits for Step A and Step B), the engine must ensure Step C is only dispatched once, even if A and B complete simultaneously.
4. **Laravel Bridge:** Provide a concrete implementation utilizing Laravel's Horizon/Redis queue system.

## Architecture

### 1. `QueueDriverInterface`
A framework-agnostic contract located in `src/Execution/Contracts/QueueDriverInterface.php`.
```php
interface QueueDriverInterface {
    public function dispatchStep(string $workflowId, string $stepId, int $delaySeconds = 0): void;
}
```

### 2. The Entrypoint: `WorkflowExecutor`
The `WorkflowExecutor::execute()` method will be refactored to act strictly as the workflow initiator:
1. Initialize the `WorkflowContext` and save it to the `StateStoreInterface`.
2. Query `DependencyAnalyzer` for the root steps (steps with 0 dependencies).
3. Call `QueueDriverInterface::dispatchStep()` for each root step.
4. Return an "In Progress" execution result immediately.

### 3. The Choreographer: `StepExecutionWorker`
The `StepExecutionWorker` becomes responsible for propelling the workflow forward. Upon successful execution of its assigned step:
1. It updates the state in the `StateStoreInterface`.
2. It calls `DependencyAnalyzer::getRunnableSteps(Workflow $workflow, array $currentState)` to identify newly unlocked steps.
3. It iterates over the newly unlocked steps and dispatches them via `QueueDriverInterface`.

*Note on Concurrency:* The worker already utilizes `LockManagerInterface` to prevent concurrent execution of the same step. To prevent double-dispatching of downstream steps, the worker will either rely on atomic state updates or acquire a short lock when evaluating and dispatching the next DAG segment.

### 4. The Laravel Implementation (The Bridge)
- **`ExecuteStepJob`**: A standard Laravel Job class (`app/Jobs/ExecuteStepJob.php` or `src/Laravel/Jobs/...`) that implements `ShouldQueue`. Its `handle()` method resolves the framework-agnostic `StepExecutionWorker` from the container and invokes it.
- **`LaravelQueueDriver`**: Implements `QueueDriverInterface`. It translates the abstract dispatch call into `dispatch(new ExecuteStepJob($workflowId, $stepId))->delay($delaySeconds)`.

## Trade-offs & Open Questions
- **Error Handling:** If a job fails and throws an exception, Laravel's queue will automatically retry it. If it fails permanently (max tries reached), a `failed()` method on the job will need to invoke the workflow's `onFailure` compensation logic. This will be addressed in a subsequent "Error Handling & Sagas" design.
- **Queue Drivers:** For testing, a `SyncQueueDriver` (which immediately executes the step) can be implemented to maintain synchronous testing capabilities without modifying the core executor.
