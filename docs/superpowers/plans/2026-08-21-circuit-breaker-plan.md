# Circuit Breaker (`maxSteps`) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a `maxSteps` circuit breaker to prevent runaway Arazzo workflows with infinite `goto` or `retry` loops.

**Architecture:** Introduce `stepsExecuted` to `WorkflowContext` for persistence across queue jumps, and enforce a configurable `$maxSteps` limit in both `WorkflowExecutor` and `StepExecutionWorker` right before step execution.

**Tech Stack:** PHP 8.4, Pest PHP

---

### Task 1: Create Exception and Update `WorkflowContext`

**Files:**
- Create: `packages/core/src/Runner/Exceptions/StepBudgetExceededException.php`
- Modify: `packages/core/src/Runner/WorkflowContext.php`

- [ ] **Step 1: Create the new exception class**

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Exceptions;

use RuntimeException;

class StepBudgetExceededException extends RuntimeException
{
}
```

- [ ] **Step 2: Update `WorkflowContext` to track execution count**

Add `$stepsExecuted` to the constructor in `packages/core/src/Runner/WorkflowContext.php` (default `0`), update all existing `with*` methods to pass it, and add getter/mutator.

```php
    public function __construct(
        private string $definitionId,
        private array $inputs = [],
        private array $steps = [],
        private array $components = [],
        private ?string $workflowId = null,
        private ?string $executionId = null,
        private int $stepsExecuted = 0,
    ) {
        // existing uniqid logic...
    }

    public function getStepsExecuted(): int
    {
        return $this->stepsExecuted;
    }

    public function withIncrementedExecutionCount(): self
    {
        return new self($this->definitionId, $this->inputs, $this->steps, $this->components, $this->workflowId, $this->executionId, $this->stepsExecuted + 1);
    }
```
*(Ensure ALL other `with...` methods in `WorkflowContext` are updated to pass `$this->stepsExecuted` to the new instance.)*

- [ ] **Step 3: Commit Task 1**

```bash
git add packages/core/src/Runner/Exceptions/StepBudgetExceededException.php packages/core/src/Runner/WorkflowContext.php
git commit -m "feat: add StepBudgetExceededException and track execution count in WorkflowContext"
```

---

### Task 2: Update `StepExecutionWorker` Serialization

**Files:**
- Modify: `packages/core/src/Runner/StepExecutionWorker.php`

- [ ] **Step 1: Persist `$stepsExecuted` in `serialize`**

```php
    private function serialize(WorkflowContext $context): array
    {
        return [
            'definitionId' => $context->getDefinitionId(),
            'workflowId' => $context->getWorkflowId(),
            'steps' => $context->getSteps(),
            'inputs' => $context->getInputs(),
            'components' => $context->getComponents(),
            'stepsExecuted' => $context->getStepsExecuted(),
        ];
    }
```

- [ ] **Step 2: Restore `$stepsExecuted` in `reconcileWithPersistedState`**

```php
    private function reconcileWithPersistedState(WorkflowContext $context, string $executionId): WorkflowContext
    {
        $persisted = $this->stateStore->load($executionId);
        if ($persisted === null) {
            return $context;
        }

        $mergedSteps = array_merge($context->getSteps(), $persisted['steps'] ?? []);

        return new WorkflowContext(
            $context->getDefinitionId(),
            $context->getInputs(),
            $mergedSteps,
            $context->getComponents(),
            $context->getWorkflowId(),
            $executionId,
            $persisted['stepsExecuted'] ?? $context->getStepsExecuted()
        );
    }
```

- [ ] **Step 3: Commit Task 2**

```bash
git add packages/core/src/Runner/StepExecutionWorker.php
git commit -m "feat: serialize stepsExecuted in async worker state"
```

---

### Task 3: Enforce `maxSteps` in Executors

**Files:**
- Modify: `packages/core/src/Runner/WorkflowExecutor.php`
- Modify: `packages/core/src/Runner/StepExecutionWorker.php`

- [ ] **Step 1: Add configuration and enforcement to `WorkflowExecutor`**

In `WorkflowExecutor.php`, add to the constructor:
```php
    public function __construct(
        private StepExecutor $stepExecutor,
        private ?ExecutionLoggerInterface $logger = null,
        ?EventDispatcherInterface $events = null,
        private int $maxSteps = 1000,
    ) {
```

Inside `execute()` loop, right before `$this->stepExecutor->execute(...)`:
```php
                if ($context->getStepsExecuted() >= $this->maxSteps) {
                    throw new \Alama\Arazzo\Runner\Exceptions\StepBudgetExceededException("Workflow exceeded the maximum step budget of {$this->maxSteps}.");
                }
                $context = $context->withIncrementedExecutionCount();
```

- [ ] **Step 2: Add configuration and enforcement to `StepExecutionWorker`**

In `StepExecutionWorker.php`, add to the constructor:
```php
        private int $stateTtlSeconds = 86400,
        private int $maxSteps = 1000,
        ?EventDispatcherInterface $events = null,
```

Inside `handle()` closure, right before `$executor->execute(...)`:
```php
                if ($context->getStepsExecuted() >= $this->maxSteps) {
                    throw new \Alama\Arazzo\Runner\Exceptions\StepBudgetExceededException("Workflow exceeded the maximum step budget of {$this->maxSteps}.");
                }
                $context = $context->withIncrementedExecutionCount();
```

- [ ] **Step 3: Run all tests to ensure they pass**

Run `composer test`.
Expected: PASS

- [ ] **Step 4: Commit Task 3**

```bash
git add packages/core/src/Runner/WorkflowExecutor.php packages/core/src/Runner/StepExecutionWorker.php
git commit -m "feat: enforce maxSteps circuit breaker in executors"
```
