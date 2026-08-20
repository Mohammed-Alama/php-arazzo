# Unify Control Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor `StepOutcomeHandler` to return pure `Transition` DTOs instead of performing side-effects (queuing/state-saving), enabling the synchronous `WorkflowExecutor` to support advanced Arazzo control flow (`goto`, `retry`, `end`) without relying on queues, and introducing a step budget circuit breaker.

**Architecture:** We will introduce a set of Transition DTOs. `StepOutcomeHandler` will become a pure service that evaluates conditions and returns a Transition. The async `StepExecutionWorker` will apply these transitions via the queue, while the sync `WorkflowExecutor` will apply them in a synchronous `while(true)` loop (using `sleep` for retries). We will also add a `$stepBudget` constraint to `WorkflowContext`.

**Tech Stack:** PHP 8.4, PSR-18, Pest

---

### Task 1: Introduce Transition DTOs and Circuit Breaker

**Files:**
- Create: `/Users/mohammedalama/Code/Me/php-arazzo/packages/core/src/Runner/Dto/Transitions/Transition.php`
- Create: `/Users/mohammedalama/Code/Me/php-arazzo/packages/core/src/Runner/Dto/Transitions/NextTransition.php`
- Create: `/Users/mohammedalama/Code/Me/php-arazzo/packages/core/src/Runner/Dto/Transitions/RetryTransition.php`
- Create: `/Users/mohammedalama/Code/Me/php-arazzo/packages/core/src/Runner/Dto/Transitions/GotoTransition.php`
- Create: `/Users/mohammedalama/Code/Me/php-arazzo/packages/core/src/Runner/Dto/Transitions/EndTransition.php`
- Modify: `/Users/mohammedalama/Code/Me/php-arazzo/packages/core/src/Runner/WorkflowContext.php`
- Modify: `/Users/mohammedalama/Code/Me/php-arazzo/packages/core/src/Runner/Exceptions/ExecutionException.php` (if not exists, create it)

- [ ] **Step 1: Create the base Transition interface/abstract class**

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Dto\Transitions;

use Alama\Arazzo\Runner\WorkflowContext;

abstract class Transition
{
    public function __construct(public readonly WorkflowContext $context)
    {
    }
}
```

- [ ] **Step 2: Create the concrete Transition classes**

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Dto\Transitions;

use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Runner\ExecutionStatus;
use Alama\Arazzo\Runner\WorkflowContext;

class NextTransition extends Transition
{
}

class GotoTransition extends Transition
{
    public function __construct(
        WorkflowContext $context,
        public readonly Step $targetStep
    ) {
        parent::__construct($context);
    }
}

class RetryTransition extends Transition
{
    public function __construct(
        WorkflowContext $context,
        public readonly Step $targetStep,
        public readonly int $delaySeconds
    ) {
        parent::__construct($context);
    }
}

class EndTransition extends Transition
{
    public function __construct(
        WorkflowContext $context,
        public readonly ExecutionStatus $status,
        public readonly ?\Throwable $error = null
    ) {
        parent::__construct($context);
    }
}
```

- [ ] **Step 3: Create ExecutionException**

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Exceptions;

use RuntimeException;

class ExecutionException extends RuntimeException
{
}
```

- [ ] **Step 4: Update `WorkflowContext` to track step budget**

Modify `WorkflowContext` constructor to accept a step budget limit and track spent budget:

```php
    public function __construct(
        private string $definitionId,
        private array $inputs = [],
        private array $steps = [],
        private array $components = [],
        private ?string $workflowId = null,
        private ?string $executionId = null,
        private int $budgetSpent = 0,
        private int $maxStepBudget = 1000,
    ) {
        if ($this->executionId === null) {
            $this->executionId = uniqid('run_', true);
        }
    }
```
Update all `with*` methods in `WorkflowContext.php` (e.g., `withWorkflowId`, `withStepResult`, etc.) to pass `$this->budgetSpent` and `$this->maxStepBudget` to the new instance.

- [ ] **Step 5: Add a `spendBudget` method to `WorkflowContext`**

```php
    public function spendBudget(): self
    {
        $newSpent = $this->budgetSpent + 1;
        if ($newSpent > $this->maxStepBudget) {
            throw new \Alama\Arazzo\Runner\Exceptions\ExecutionException("Workflow '{$this->workflowId}' exceeded its budget of {$this->maxStepBudget} step attempts.");
        }

        return new self($this->definitionId, $this->inputs, $this->steps, $this->components, $this->workflowId, $this->executionId, $newSpent, $this->maxStepBudget);
    }
```

- [ ] **Step 6: Update `serialize` method in `StepExecutionWorker` and `StepOutcomeHandler` if they exist to include `maxStepBudget` and `budgetSpent`**
*(Note: Ensure serialization keeps the state. Modify `serialize` in `StepExecutionWorker.php`)*
```php
    private function serialize(WorkflowContext $context): array
    {
        return [
            'definitionId' => $context->getDefinitionId(),
            'workflowId' => $context->getWorkflowId(),
            'steps' => $context->getSteps(),
            'inputs' => $context->getInputs(),
            'components' => $context->getComponents(),
            // Ensure you use reflection or add getters for budget
        ];
    }
```
Add `getBudgetSpent(): int` and `getMaxStepBudget(): int` to `WorkflowContext.php`.

- [ ] **Step 7: Commit Task 1**

```bash
git add packages/core/src/Runner
git commit -m "feat: introduce Transition DTOs and step budget circuit breaker"
```

---

### Task 2: Refactor `StepOutcomeHandler` to be pure

**Files:**
- Modify: `/Users/mohammedalama/Code/Me/php-arazzo/packages/core/src/Runner/StepOutcomeHandler.php`

- [ ] **Step 1: Simplify Constructor Dependencies**

Remove `QueueDriverInterface`, `Engine`, `StateStoreInterface`, `ExecutionRegistryInterface`, `EventLedgerInterface`. Keep resolvers and evaluators. Keep `EventDispatcherInterface` for events that don't fit into Transitions.

```php
    public function __construct(
        private PendingCorrelationRegistryInterface $pendingCorrelations,
        private ExpressionResolverInterface $expressionResolver,
        private SubWorkflowInvoker $invoker,
        private SelectorEvaluator $selectors,
        private ExpressionEvaluator $expressions,
        private int $maxRetryAttempts = 10,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new NullEventDispatcher();
    }
```

- [ ] **Step 2: Update Return Type of `handle`**

Change `handle` to return `\Alama\Arazzo\Runner\Dto\Transitions\Transition`.
```php
    public function handle(
        ArazzoDocument $document,
        Workflow $workflow,
        Step $step,
        WorkflowContext $context,
        string $executionId,
        bool $criteriaMet,
    ): \Alama\Arazzo\Runner\Dto\Transitions\Transition {
        // ... (existing output resolution logic)
        return $this->applyFirstMatch($actions, $document, $workflow, $step, $context, $executionId, $criteriaMet);
    }
```

- [ ] **Step 3: Refactor `applyFirstMatch` and `terminate` to return Transitions**

Instead of calling `$this->queueDriver->dispatch(...)` or `$this->stateStore->save(...)`, return the corresponding Transition DTO. For example:

```php
    private function applyFirstMatch(/* ... */): Transition {
        $matched = $this->firstMatchingAction($actions, $step, $context, $document);

        if ($matched === null) {
            if ($criteriaMet) {
                return $this->continueNormally($workflow, $step, $context, $executionId);
            } else {
                return new \Alama\Arazzo\Runner\Dto\Transitions\EndTransition($context, \Alama\Arazzo\Runner\ExecutionStatus::Failed, new \RuntimeException("execution.failed"));
            }
        }
        
        if ($matched instanceof RetryAction) {
            return $this->handleRetry($matched, $actions, $document, $workflow, $step, $context, $executionId, $criteriaMet);
        }

        if ($matched instanceof SuccessGotoAction || $matched instanceof FailureGotoAction) {
            $status = $matched instanceof SuccessGotoAction ? StepStatus::Succeeded : StepStatus::Failed;
            $newContext = $context->withStepStatus($step->stepId, $status);
            return $this->handleGoto($matched, $document, $newContext, $executionId);
        }

        if ($matched instanceof SuccessEndAction || $matched instanceof FailureEndAction) {
            $status = $matched instanceof SuccessEndAction ? \Alama\Arazzo\Runner\ExecutionStatus::Succeeded : \Alama\Arazzo\Runner\ExecutionStatus::Failed;
            return new \Alama\Arazzo\Runner\Dto\Transitions\EndTransition($context, $status);
        }

        if ($matched instanceof SubWorkflowSuccessAction || $matched instanceof SubWorkflowFailureAction) {
            $result = $this->invoker->invoke($matched, $context);
            $context = $context->withStepOutput($step->stepId, $matched->name, $result->outputs);
            return $this->continueNormally($workflow, $step, $context, $executionId);
        }
        // ...
    }
```

- [ ] **Step 4: Refactor `handleGoto`, `handleRetry`, `continueNormally`**

`continueNormally` should return `new NextTransition($newContext)`.
`handleGoto` should find target step and return `new GotoTransition($newContext, $targetStep)` (if stepId provided) or `new NextTransition($newContext)` (if workflowId provided).
`handleRetry` should return `new RetryTransition($newContext, $targetStep, $action->retryAfter ?? 0)`.

- [ ] **Step 5: Run tests and fix compilation errors**
Run `composer test` and `composer analyse` to fix any mock mismatches in `StepOutcomeHandlerTest.php`.

- [ ] **Step 6: Commit Task 2**

```bash
git add packages/core/src/Runner/StepOutcomeHandler.php packages/core/tests
git commit -m "refactor: make StepOutcomeHandler pure by returning Transitions"
```

---

### Task 3: Apply Transitions in Async `StepExecutionWorker`

**Files:**
- Modify: `/Users/mohammedalama/Code/Me/php-arazzo/packages/core/src/Runner/StepExecutionWorker.php`

- [ ] **Step 1: Handle returned Transition in `handle()`**

```php
                $transition = $this->outcomeHandler->handle($document, $workflow, $step, $contextWithResult, $executionId, $criteriaMet);
                
                // State saving moved here
                $this->stateStore->save($executionId, $this->serialize($transition->context), $this->stateTtlSeconds);

                if ($transition instanceof \Alama\Arazzo\Runner\Dto\Transitions\RetryTransition) {
                    $this->queueDriver->dispatch(new ExecuteStepJob($transition->targetStep, $transition->context), $transition->delaySeconds);
                } elseif ($transition instanceof \Alama\Arazzo\Runner\Dto\Transitions\GotoTransition) {
                    $this->queueDriver->dispatch(new ExecuteStepJob($transition->targetStep, $transition->context));
                } elseif ($transition instanceof \Alama\Arazzo\Runner\Dto\Transitions\NextTransition) {
                    // Start Engine evaluation for next steps
                    $this->executionRegistry->start($executionId, $transition->context->getDefinitionId(), $transition->context->getWorkflowId());
                    // Assume you need to resolve engine or inject it
                    // Note: StepExecutionWorker will need $this->engine back!
                    $this->engine->evaluate($workflow, $transition->context);
                } elseif ($transition instanceof \Alama\Arazzo\Runner\Dto\Transitions\EndTransition) {
                    $this->executionRegistry->complete($executionId, $transition->status);
                    // Emit RunCompleted / RunFailed events
                }
```
*(Ensure `StepExecutionWorker` retains or gets `Engine` and `EventLedgerInterface` injected if required to perform these side effects).*

- [ ] **Step 2: Add budget charging to `handle()`**

Right before calling `$executor->execute()`, charge the budget:
```php
    $context = $context->spendBudget();
```
*(Catch `ExecutionException` and fail the run if exhausted).*

- [ ] **Step 3: Run tests to verify Async Worker works with Transitions**

- [ ] **Step 4: Commit Task 3**

```bash
git add packages/core/src/Runner/StepExecutionWorker.php
git commit -m "feat: apply transitions in async worker"
```

---

### Task 4: Upgrade Synchronous `WorkflowExecutor`

**Files:**
- Modify: `/Users/mohammedalama/Code/Me/php-arazzo/packages/core/src/Runner/WorkflowExecutor.php`

- [ ] **Step 1: Inject dependencies for `WorkflowExecutor`**

Inject `StepOutcomeHandler`, `DependencyAnalyzer` into `WorkflowExecutor`.

```php
    public function __construct(
        private StepExecutor $stepExecutor,
        private StepOutcomeHandler $outcomeHandler,
        private ?ExecutionLoggerInterface $logger = null,
        ?EventDispatcherInterface $events = null,
    ) {
```

- [ ] **Step 2: Rewrite `execute()` loop**

Replace topological sort with a `while` loop that finds the next step and handles transitions.
```php
        $graph = new DependencyGraph($workflow->steps);
        $analyzer = new DependencyAnalyzer($graph);

        while (true) {
            $runnableSteps = $analyzer->getRunnableSteps($context);
            if (empty($runnableSteps)) {
                break; // Complete
            }

            // Sync execution processes one step at a time
            $step = $runnableSteps[0]; 

            $context = $context->spendBudget();

            // Run step
            [$context, $success] = $this->stepExecutor->execute($step, $context, $document);

            // Handle outcome
            $transition = $this->outcomeHandler->handle($document, $workflow, $step, $context, $executionId, $success);
            
            $context = $transition->context;

            if ($transition instanceof \Alama\Arazzo\Runner\Dto\Transitions\RetryTransition) {
                if ($transition->delaySeconds > 0) {
                    sleep($transition->delaySeconds);
                }
                continue; // Next iteration will pick up the Pending targetStep
            }
            
            if ($transition instanceof \Alama\Arazzo\Runner\Dto\Transitions\GotoTransition) {
                continue; // Next iteration will pick up the Pending targetStep
            }

            if ($transition instanceof \Alama\Arazzo\Runner\Dto\Transitions\EndTransition) {
                if ($transition->status === \Alama\Arazzo\Runner\ExecutionStatus::Failed) {
                    throw $transition->error ?? new \RuntimeException("Execution failed");
                }
                break;
            }
            
            // NextTransition simply continues the loop
        }
```

- [ ] **Step 3: Test Synchronous Execution**
Ensure all existing sync tests pass, and add a test verifying `goto` and `retry` now work in the synchronous `WorkflowExecutor`.

- [ ] **Step 4: Commit Task 4**

```bash
git add packages/core/src/Runner/WorkflowExecutor.php
git commit -m "feat: support advanced control flow in synchronous WorkflowExecutor"
```
