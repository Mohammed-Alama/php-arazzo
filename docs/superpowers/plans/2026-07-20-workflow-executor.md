# Workflow Executor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a framework-agnostic execution engine for Arazzo workflows using PSR standards.

**Architecture:** The executor will be isolated in the `Alama\LaravelArazzo\Execution` namespace. It relies on PSR-18/17/3 interfaces for HTTP and Logging to remain decoupled from Laravel. It consumes parsed `Workflow` DTOs and evaluates expressions using an `ExpressionEvaluator`.

**Tech Stack:** PHP 8.4, PSR-18, PSR-17, PSR-3, `flow/jsonpath`

---

### Task 1: Add Dependencies

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Require PSR interfaces and JSONPath in composer.json**

Open `composer.json` and add the following to the `require` block:
```json
        "psr/http-client": "^1.0",
        "psr/http-factory": "^1.0",
        "psr/http-message": "^1.0||^2.0",
        "psr/log": "^3.0",
        "flow/jsonpath": "^0.5.0"
```

- [ ] **Step 2: Update composer**

```bash
rtk proxy composer update
```

### Task 2: Contracts and DTOs

**Files:**
- Create: `src/Execution/Contracts/StateStoreInterface.php`
- Create: `src/Execution/Contracts/ExecutionLoggerInterface.php`
- Create: `src/Execution/Dto/ExecutionResult.php`
- Create: `src/Execution/Dto/StepResult.php`

- [ ] **Step 1: Define `StepResult` DTO**

```php
<?php

namespace Alama\LaravelArazzo\Execution\Dto;

class StepResult
{
    public function __construct(
        public readonly string $stepId,
        public readonly bool $success,
        public readonly array $outputs = [],
        public readonly ?\Throwable $error = null
    ) {}
}
```

- [ ] **Step 2: Define `ExecutionResult` DTO**

```php
<?php

namespace Alama\LaravelArazzo\Execution\Dto;

class ExecutionResult
{
    public function __construct(
        public readonly string $workflowId,
        public readonly string $status,
        public readonly array $outputs,
        /** @var array<string, StepResult> */
        public readonly array $stepResults
    ) {}
}
```

- [ ] **Step 3: Define Contracts**

```php
<?php

namespace Alama\LaravelArazzo\Execution\Contracts;

interface StateStoreInterface
{
    public function save(string $executionId, array $state): void;
    public function load(string $executionId): ?array;
}
```

```php
<?php

namespace Alama\LaravelArazzo\Execution\Contracts;

use Throwable;

interface ExecutionLoggerInterface
{
    public function logStepStarted(string $stepId): void;
    public function logStepCompleted(string $stepId, array $outputs): void;
    public function logStepFailed(string $stepId, Throwable $error): void;
}
```

### Task 3: Variable Context & Evaluator

**Files:**
- Create: `src/Execution/VariableContext.php`
- Create: `src/Execution/ExpressionEvaluator.php`

- [ ] **Step 1: Write `VariableContext`**

```php
<?php

namespace Alama\LaravelArazzo\Execution;

class VariableContext
{
    public function __construct(
        private array $inputs = [],
        private array $steps = [],
        private array $components = []
    ) {}

    public function setInput(string $key, mixed $value): void
    {
        $this->inputs[$key] = $value;
    }

    public function getInputs(): array
    {
        return $this->inputs;
    }

    public function setStepOutput(string $stepId, string $key, mixed $value): void
    {
        $this->steps[$stepId]['outputs'][$key] = $value;
    }

    public function getSteps(): array
    {
        return $this->steps;
    }
}
```

- [ ] **Step 2: Write `ExpressionEvaluator` Stub**

```php
<?php

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Expression;

class ExpressionEvaluator
{
    public function __construct(private VariableContext $context) {}

    public function evaluate(Expression $expression): mixed
    {
        // To be implemented: Ast visitor mapped to context
        return null; 
    }
}
```

### Task 4: Dependency Graph

**Files:**
- Create: `src/Execution/DependencyGraph.php`
- Create: `tests/Execution/DependencyGraphTest.php`

- [ ] **Step 1: Write failing test for DependencyGraph**

```php
<?php

use Alama\LaravelArazzo\Execution\DependencyGraph;
use Alama\LaravelArazzo\Dto\Step;

it('sorts steps topologically', function () {
    $step1 = new Step(
        stepId: 'step1',
        description: null,
        operationId: 'op1',
        operationPath: null,
        workflowId: null,
        dependsOn: [],
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: []
    );
    
    $step2 = new Step(
        stepId: 'step2',
        description: null,
        operationId: 'op2',
        operationPath: null,
        workflowId: null,
        dependsOn: ['step1'],
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: []
    );
    
    $graph = new DependencyGraph([$step2, $step1]);
    $order = $graph->getExecutionOrder();
    
    expect($order)->toBe(['step1', 'step2']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `rtk proxy vendor/bin/pest tests/Execution/DependencyGraphTest.php`
Expected: FAIL (Class not found)

- [ ] **Step 3: Implement DependencyGraph**

```php
<?php

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Step;
use RuntimeException;

class DependencyGraph
{
    /** @var array<string, Step> */
    private array $steps = [];

    public function __construct(array $steps)
    {
        foreach ($steps as $step) {
            $this->steps[$step->stepId] = $step;
        }
    }

    public function getExecutionOrder(): array
    {
        $order = [];
        $visited = [];
        $visiting = [];

        $visit = function ($stepId) use (&$visit, &$order, &$visited, &$visiting) {
            if (isset($visited[$stepId])) return;
            if (isset($visiting[$stepId])) throw new RuntimeException("Circular dependency detected");

            $visiting[$stepId] = true;
            $step = $this->steps[$stepId] ?? null;
            if ($step) {
                foreach ($step->dependsOn ?? [] as $dep) {
                    $visit($dep);
                }
            }
            unset($visiting[$stepId]);
            $visited[$stepId] = true;
            $order[] = $stepId;
        };

        foreach (array_keys($this->steps) as $stepId) {
            $visit($stepId);
        }

        return $order;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `rtk proxy vendor/bin/pest tests/Execution/DependencyGraphTest.php`
Expected: PASS

### Task 5: Step Executor & Workflow Executor

**Files:**
- Create: `src/Execution/StepExecutor.php`
- Create: `src/Execution/WorkflowExecutor.php`

- [ ] **Step 1: Implement `StepExecutor`**

```php
<?php

namespace Alama\LaravelArazzo\Execution;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Dto\StepResult;

class StepExecutor
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private ExpressionEvaluator $evaluator
    ) {}

    public function execute(Step $step, VariableContext $context): StepResult
    {
        // This is a stub for the core layout.
        // In the next iterations, we map parameters and invoke PSR-18 client
        return new StepResult($step->stepId, true);
    }
}
```

- [ ] **Step 2: Implement `WorkflowExecutor`**

```php
<?php

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Dto\ExecutionResult;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionLoggerInterface;

class WorkflowExecutor
{
    public function __construct(
        private StepExecutor $stepExecutor,
        private ?ExecutionLoggerInterface $logger = null
    ) {}

    public function execute(Workflow $workflow, array $inputs): ExecutionResult
    {
        $context = new VariableContext($inputs);
        $graph = new DependencyGraph($workflow->steps);
        $order = $graph->getExecutionOrder();

        $stepResults = [];

        foreach ($order as $stepId) {
            $step = $this->findStep($workflow->steps, $stepId);
            $this->logger?->logStepStarted($stepId);
            
            $result = $this->stepExecutor->execute($step, $context);
            $stepResults[$stepId] = $result;

            if (!$result->success) {
                $this->logger?->logStepFailed($stepId, $result->error ?? new \RuntimeException("Step failed"));
                break;
            }
            
            $this->logger?->logStepCompleted($stepId, $result->outputs);
        }

        return new ExecutionResult($workflow->workflowId, 'completed', [], $stepResults);
    }

    private function findStep(array $steps, string $stepId): ?Step
    {
        foreach ($steps as $step) {
            if ($step->stepId === $stepId) return $step;
        }
        return null;
    }
}
```

- [ ] **Step 3: Commit Progress**

```bash
rtk proxy git add src/Execution tests/Execution composer.json
rtk proxy git commit -m "feat: Add core skeleton for workflow execution engine"
```
