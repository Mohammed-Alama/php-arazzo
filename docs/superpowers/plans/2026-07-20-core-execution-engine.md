# Core Execution Engine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor the synchronous Arazzo executor into an asynchronous, framework-agnostic Event-Driven DAG Engine with Scatter-Gather parallelism.

**Architecture:** We will define pure PHP interfaces for Queues, Locks, and HTTP. We will build a Dependency Analyzer to parse the DAG, an Engine to orchestrate execution, and immutable Contexts. Finally, we provide Laravel-specific adapters for queues and locking.

**Tech Stack:** PHP 8.2+, Pest/PHPUnit, Laravel (for Adapters only), PSR-18 HTTP Client.

---

### Task 1: Define Framework-Agnostic Interfaces

**Files:**
- Create: `src/Execution/Contracts/QueueDriverInterface.php`
- Create: `src/Execution/Contracts/LockManagerInterface.php`
- Create: `src/Execution/Contracts/HttpClientInterface.php`
- Test: `tests/Unit/Execution/ContractsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use PHPUnit\Framework\TestCase;

class ContractsTest extends TestCase
{
    public function test_interfaces_exist(): void
    {
        $this->assertTrue(interface_exists(QueueDriverInterface::class));
        $this->assertTrue(interface_exists(LockManagerInterface::class));
        $this->assertTrue(interface_exists(HttpClientInterface::class));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Execution/ContractsTest.php`
Expected: FAIL with "Interface ... not found"

- [ ] **Step 3: Write minimal implementation**

```php
// src/Execution/Contracts/QueueDriverInterface.php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution\Contracts;

interface QueueDriverInterface
{
    public function dispatch(object $job, int $delaySeconds = 0): void;
}

// src/Execution/Contracts/LockManagerInterface.php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution\Contracts;

interface LockManagerInterface
{
    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed;
}

// src/Execution/Contracts/HttpClientInterface.php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution\Contracts;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface HttpClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Execution/ContractsTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/Execution/ContractsTest.php src/Execution/Contracts/
git commit -m "feat: define framework agnostic interfaces for core engine"
```

---

### Task 2: Refactor Context into Immutable WorkflowContext

**Files:**
- Modify: `src/Execution/VariableContext.php` (convert to immutable or create `WorkflowContext.php`)
- Create: `src/Execution/WorkflowContext.php`
- Test: `tests/Unit/Execution/WorkflowContextTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Execution\WorkflowContext;
use PHPUnit\Framework\TestCase;

class WorkflowContextTest extends TestCase
{
    public function test_immutability(): void
    {
        $context = new WorkflowContext('def_1', ['id' => 1]);
        $newContext = $context->withStepResult('step_1', ['success' => true]);
        
        $this->assertNotSame($context, $newContext);
        $this->assertEmpty($context->getSteps());
        $this->assertEquals(['success' => true], $newContext->getSteps()['step_1']);
        $this->assertEquals('def_1', $newContext->getDefinitionId());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Execution/WorkflowContextTest.php`
Expected: FAIL "Class WorkflowContext not found"

- [ ] **Step 3: Write minimal implementation**

```php
// src/Execution/WorkflowContext.php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution;

final class WorkflowContext
{
    public function __construct(
        private string $definitionId,
        private array $inputs = [],
        private array $steps = [],
        private array $components = []
    ) {}

    public function getDefinitionId(): string { return $this->definitionId; }
    public function getInputs(): array { return $this->inputs; }
    public function getSteps(): array { return $this->steps; }
    public function getComponents(): array { return $this->components; }

    public function withStepResult(string $stepId, array $result): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId] = $result;
        return new self($this->definitionId, $this->inputs, $newSteps, $this->components);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Execution/WorkflowContextTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Execution/WorkflowContext.php tests/Unit/Execution/WorkflowContextTest.php
git commit -m "feat: add immutable workflow context"
```

---

### Task 3: Build the DependencyAnalyzer

**Files:**
- Create: `src/Execution/DependencyAnalyzer.php`
- Test: `tests/Unit/Execution/DependencyAnalyzerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Dto\Step;
use PHPUnit\Framework\TestCase;

class DependencyAnalyzerTest extends TestCase
{
    public function test_finds_runnable_steps(): void
    {
        $stepA = new Step(); $stepA->stepId = 'A'; $stepA->dependsOn = [];
        $stepB = new Step(); $stepB->stepId = 'B'; $stepB->dependsOn = ['A'];
        
        $analyzer = new DependencyAnalyzer();
        
        // Initial state
        $context = new WorkflowContext('def_1');
        $runnable = $analyzer->getRunnableSteps([$stepA, $stepB], $context);
        $this->assertCount(1, $runnable);
        $this->assertEquals('A', $runnable[0]->stepId);

        // State after A completes
        $context2 = $context->withStepResult('A', ['outputs' => []]);
        $runnable2 = $analyzer->getRunnableSteps([$stepA, $stepB], $context2);
        $this->assertCount(1, $runnable2);
        $this->assertEquals('B', $runnable2[0]->stepId);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Execution/DependencyAnalyzerTest.php`
Expected: FAIL "Class DependencyAnalyzer not found"

- [ ] **Step 3: Write minimal implementation**

```php
// src/Execution/DependencyAnalyzer.php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Step;

class DependencyAnalyzer
{
    /**
     * @param Step[] $allSteps
     * @return Step[]
     */
    public function getRunnableSteps(array $allSteps, WorkflowContext $context): array
    {
        $runnable = [];
        $completedStepIds = array_keys($context->getSteps());

        foreach ($allSteps as $step) {
            // If already completed, skip
            if (in_array($step->stepId, $completedStepIds, true)) {
                continue;
            }

            // If no dependencies, it's runnable
            if (empty($step->dependsOn)) {
                $runnable[] = $step;
                continue;
            }

            // Check if all dependencies are completed
            $dependenciesMet = true;
            foreach ($step->dependsOn as $dependencyId) {
                if (!in_array($dependencyId, $completedStepIds, true)) {
                    $dependenciesMet = false;
                    break;
                }
            }

            if ($dependenciesMet) {
                $runnable[] = $step;
            }
        }

        return $runnable;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Execution/DependencyAnalyzerTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Execution/DependencyAnalyzer.php tests/Unit/Execution/DependencyAnalyzerTest.php
git commit -m "feat: implement dependency analyzer for DAG evaluation"
```

---

### Task 4: Create the Event-Driven Engine

**Files:**
- Create: `src/Execution/Engine.php`
- Test: `tests/Unit/Execution/EngineTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Dto\Step;
use PHPUnit\Framework\TestCase;

class MockQueueDriver implements QueueDriverInterface {
    public array $dispatched = [];
    public function dispatch(object $job, int $delaySeconds = 0): void {
        $this->dispatched[] = $job;
    }
}

class MockStateStore implements StateStoreInterface {
    public function save(string $id, array $state): void {}
    public function load(string $id): array { return []; }
}

class EngineTest extends TestCase
{
    public function test_engine_dispatches_runnable_steps(): void
    {
        $queue = new MockQueueDriver();
        $store = new MockStateStore();
        $analyzer = new DependencyAnalyzer();
        $engine = new Engine($analyzer, $queue, $store);

        $workflow = new Workflow();
        $stepA = new Step(); $stepA->stepId = 'A'; $stepA->dependsOn = [];
        $stepB = new Step(); $stepB->stepId = 'B'; $stepB->dependsOn = [];
        $workflow->steps = [$stepA, $stepB];

        $context = new WorkflowContext('def_1');
        
        $engine->evaluate($workflow, $context);

        $this->assertCount(2, $queue->dispatched);
        // We assert they are dispatched as stdClass or specific Job classes, for now let's say ExecuteStepJob
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Execution/EngineTest.php`
Expected: FAIL "Class Engine not found"

- [ ] **Step 3: Write minimal implementation**

```php
// src/Execution/Jobs/ExecuteStepJob.php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution\Jobs;

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\WorkflowContext;

class ExecuteStepJob
{
    public function __construct(
        public Step $step,
        public WorkflowContext $context
    ) {}
}

// src/Execution/Engine.php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;

class Engine
{
    public function __construct(
        private DependencyAnalyzer $analyzer,
        private QueueDriverInterface $queueDriver,
        private StateStoreInterface $stateStore
    ) {}

    public function evaluate(Workflow $workflow, WorkflowContext $context): void
    {
        $runnableSteps = $this->analyzer->getRunnableSteps($workflow->steps, $context);

        if (empty($runnableSteps)) {
            // Workflow complete or waiting. We will handle completion logic later.
            return;
        }

        foreach ($runnableSteps as $step) {
            $job = new ExecuteStepJob($step, $context);
            $this->queueDriver->dispatch($job);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Execution/EngineTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Execution/Engine.php src/Execution/Jobs/ExecuteStepJob.php tests/Unit/Execution/EngineTest.php
git commit -m "feat: implement event-driven workflow engine orchestrator"
```

---

### Task 5: Implement Laravel Queue & Lock Adapters

**Files:**
- Create: `src/Laravel/LaravelQueueDriver.php`
- Create: `src/Laravel/LaravelRedisLockManager.php`
- Test: `tests/Unit/Laravel/AdaptersTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Unit\Laravel;

use Alama\LaravelArazzo\Laravel\LaravelQueueDriver;
use Alama\LaravelArazzo\Laravel\LaravelRedisLockManager;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase; // Assuming Orchestra Testbench is configured in existing project

class AdaptersTest extends TestCase
{
    public function test_queue_dispatch(): void
    {
        Queue::fake();
        $driver = new LaravelQueueDriver();
        $job = new \stdClass();
        $driver->dispatch($job);
        Queue::assertPushed(\stdClass::class);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Laravel/AdaptersTest.php`
Expected: FAIL "Class LaravelQueueDriver not found"

- [ ] **Step 3: Write minimal implementation**

```php
// src/Laravel/LaravelQueueDriver.php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Illuminate\Support\Facades\Queue;

class LaravelQueueDriver implements QueueDriverInterface
{
    public function dispatch(object $job, int $delaySeconds = 0): void
    {
        if ($delaySeconds > 0) {
            Queue::later(now()->addSeconds($delaySeconds), $job);
        } else {
            Queue::push($job);
        }
    }
}

// src/Laravel/LaravelRedisLockManager.php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Illuminate\Support\Facades\Cache;

class LaravelRedisLockManager implements LockManagerInterface
{
    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return Cache::lock($key, $ttlSeconds)->block(5, $callback);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Laravel/AdaptersTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Laravel/ tests/Unit/Laravel/
git commit -m "feat: implement laravel adapters for queue and locks"
```
