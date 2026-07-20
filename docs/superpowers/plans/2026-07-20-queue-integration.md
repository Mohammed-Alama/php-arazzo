# Queue Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transition the Arazzo engine from synchronous loop execution to a highly concurrent, event-driven DAG execution model via decoupled queue dispatching.

**Architecture:** We will implement decentralized choreography. The `WorkflowExecutor` initializes the state and dispatches the root steps. The `StepExecutionWorker` processes a step, updates the state, and immediately dispatches any newly unlocked downstream steps via a `QueueDriverInterface`.

**Tech Stack:** PHP 8.2+, Pest/PHPUnit, Laravel Queues (for the bridge).

---

### Task 1: QueueDriverInterface & SyncQueueDriver

**Files:**
- Create: `src/Execution/Contracts/QueueDriverInterface.php`
- Create: `src/Execution/SyncQueueDriver.php`
- Create: `tests/Unit/Execution/SyncQueueDriverTest.php`

- [ ] **Step 1: Write the interface and failing test**

Create `src/Execution/Contracts/QueueDriverInterface.php`:
```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution\Contracts;

interface QueueDriverInterface
{
    public function dispatchStep(string $workflowId, string $stepId, int $delaySeconds = 0): void;
}
```

Create `tests/Unit/Execution/SyncQueueDriverTest.php`:
```php
<?php
namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Execution\SyncQueueDriver;
use PHPUnit\Framework\TestCase;

class SyncQueueDriverTest extends TestCase
{
    public function test_records_dispatched_steps(): void
    {
        $driver = new SyncQueueDriver();
        $driver->dispatchStep('wf_1', 'step_A', 5);
        
        $this->assertCount(1, $driver->dispatched);
        $this->assertEquals([
            'workflowId' => 'wf_1',
            'stepId' => 'step_A',
            'delaySeconds' => 5
        ], $driver->dispatched[0]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Execution/SyncQueueDriverTest.php`
Expected: Error indicating `SyncQueueDriver` not found.

- [ ] **Step 3: Write minimal implementation**

Create `src/Execution/SyncQueueDriver.php`:
```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;

class SyncQueueDriver implements QueueDriverInterface
{
    public array $dispatched = [];

    public function dispatchStep(string $workflowId, string $stepId, int $delaySeconds = 0): void
    {
        $this->dispatched[] = [
            'workflowId' => $workflowId,
            'stepId' => $stepId,
            'delaySeconds' => $delaySeconds
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Execution/SyncQueueDriverTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Execution/Contracts/QueueDriverInterface.php src/Execution/SyncQueueDriver.php tests/Unit/Execution/SyncQueueDriverTest.php
git commit -m "feat: add QueueDriverInterface and SyncQueueDriver"
```

---

### Task 2: Refactor WorkflowExecutor

**Files:**
- Modify: `src/Execution/WorkflowExecutor.php`
- Modify: `tests/Execution/WorkflowExecutorTest.php`

- [ ] **Step 1: Write the failing test**

Modify `tests/Execution/WorkflowExecutorTest.php` to assert that `execute()` dispatches root steps instead of synchronous loop:
```php
<?php
namespace Tests\Execution;

use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\WorkflowExecutor;
use Alama\LaravelArazzo\Execution\SyncQueueDriver;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use PHPUnit\Framework\TestCase;

class WorkflowExecutorTest extends TestCase
{
    public function test_it_dispatches_root_steps(): void
    {
        $stepA = new Step('stepA', null, null, null, null, [], null, [], [], [], []);
        $stepB = new Step('stepB', null, null, null, null, [], null, [], [], [], ['stepA']);
        $workflow = new Workflow('wf_1', null, null, null, [], [$stepA, $stepB], [], [], [], [], []);
        $doc = new ArazzoDocument('1.0', [], ['wf_1' => $workflow], []);

        $analyzer = new DependencyAnalyzer();
        $queue = new SyncQueueDriver();
        $stateStore = $this->createMock(StateStoreInterface::class);
        $stateStore->expects($this->once())->method('save');

        $executor = new WorkflowExecutor($analyzer, $queue, $stateStore);
        
        $result = $executor->execute($workflow, $doc, ['input1' => 'value']);
        
        $this->assertEquals('in_progress', $result->status);
        $this->assertCount(1, $queue->dispatched);
        $this->assertEquals('stepA', $queue->dispatched[0]['stepId']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Execution/WorkflowExecutorTest.php`
Expected: FAIL due to missing constructor arguments and failing assertions.

- [ ] **Step 3: Write minimal implementation**

Modify `src/Execution/WorkflowExecutor.php`:
```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Dto\ExecutionResult;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;

class WorkflowExecutor
{
    public function __construct(
        private DependencyAnalyzer $analyzer,
        private QueueDriverInterface $queue,
        private StateStoreInterface $stateStore
    ) {}

    public function execute(Workflow $workflow, \Alama\LaravelArazzo\Dto\ArazzoDocument $document, array $inputs): ExecutionResult
    {
        // 1. Initialize State
        $this->stateStore->save($workflow->workflowId, [
            'inputs' => $inputs,
            'steps' => []
        ]);

        // 2. Get Root Steps
        $runnableSteps = $this->analyzer->getRunnableSteps($workflow, []);

        // 3. Dispatch
        foreach ($runnableSteps as $step) {
            $this->queue->dispatchStep($workflow->workflowId, $step->stepId);
        }

        return new ExecutionResult($workflow->workflowId, 'in_progress', [], []);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Execution/WorkflowExecutorTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Execution/WorkflowExecutor.php tests/Execution/WorkflowExecutorTest.php
git commit -m "feat: refactor WorkflowExecutor to dispatch queue jobs"
```

---

### Task 3: Refactor StepExecutionWorker (Choreographer)

**Files:**
- Modify: `src/Execution/StepExecutionWorker.php`
- Modify: `tests/Unit/Execution/StepExecutionWorkerTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/Execution/StepExecutionWorkerTest.php`:
```php
use Alama\LaravelArazzo\Execution\SyncQueueDriver;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;

    public function test_dispatches_downstream_steps_after_success(): void
    {
        $stepA = new \Alama\LaravelArazzo\Dto\Step('stepA', null, null, null, null, [], null, [], [], [], []);
        $stepB = new \Alama\LaravelArazzo\Dto\Step('stepB', null, null, null, null, [], null, [], [], [], ['stepA']);
        $workflow = new \Alama\LaravelArazzo\Dto\Workflow('wf_1', null, null, null, [], [$stepA, $stepB], [], [], [], [], []);

        $lockManager = $this->createMock(\Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface::class);
        $lockManager->method('acquire')->willReturn(true);
        
        $stateStore = new class implements \Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface {
            public array $state = ['steps' => []];
            public function load(string $id): array { return $this->state; }
            public function save(string $id, array $state): void { $this->state = $state; }
        };

        $analyzer = new DependencyAnalyzer();
        $queue = new SyncQueueDriver();

        $worker = new \Alama\LaravelArazzo\Execution\StepExecutionWorker($lockManager, $stateStore, clone $queue, clone $analyzer);
        // Normally we'd use reflection or pass them in a constructor if refactored, assuming constructor DI
        $worker = new \Alama\LaravelArazzo\Execution\StepExecutionWorker($lockManager, $stateStore, clone $queue, clone $analyzer); // Will need to update constructor

        $result = $worker->execute($stepA, $workflow);

        $this->assertTrue($result->success);
        $this->assertCount(1, $worker->getQueue()->dispatched ?? []); // Assuming we add a getter or use reflection
    }
```
*Note for engineer: The test will need actual mock adjustments depending on existing `StepExecutionWorker` constructor. Focus on asserting `QueueDriverInterface->dispatchStep()` is called for `stepB`.*

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Execution/StepExecutionWorkerTest.php`
Expected: FAIL

- [ ] **Step 3: Write minimal implementation**

Modify `src/Execution/StepExecutionWorker.php` constructor to accept `$analyzer` and `$queue`, and update `execute()`:
```php
    public function __construct(
        private Contracts\LockManagerInterface $locks,
        private Contracts\StateStoreInterface $stateStore,
        private Contracts\QueueDriverInterface $queue,
        private DependencyAnalyzer $analyzer
    ) {}

    // Inside execute() after saving success state...
    public function execute(Step $step, Workflow $workflow): \Alama\LaravelArazzo\Execution\Dto\StepResult
    {
        // ... (existing lock and state execution) ...
        
        $result = new \Alama\LaravelArazzo\Execution\Dto\StepResult($step->stepId, true, [], []);
        
        // Save success state
        $state = $this->stateStore->load($workflow->workflowId);
        $state['steps'][$step->stepId] = ['status' => 'success', 'outputs' => []];
        $this->stateStore->save($workflow->workflowId, $state);

        // Choreography: Find newly unlocked steps
        $runnableSteps = $this->analyzer->getRunnableSteps($workflow, $state['steps']);
        foreach ($runnableSteps as $nextStep) {
            // We only want to dispatch steps that are NOT already started/completed.
            if (!isset($state['steps'][$nextStep->stepId])) {
                $this->queue->dispatchStep($workflow->workflowId, $nextStep->stepId);
            }
        }

        // ... release lock
        return $result;
    }
```
*(Adjust according to exact existing `StepExecutionWorker` code).*

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Execution/StepExecutionWorkerTest.php`
Expected: PASS (Fix any DI issues in existing tests)

- [ ] **Step 5: Commit**

```bash
git add src/Execution/StepExecutionWorker.php tests/Unit/Execution/StepExecutionWorkerTest.php
git commit -m "feat: implement decentralized DAG dispatching in worker"
```

---

### Task 4: Laravel Bridge (Job & Driver)

**Files:**
- Create: `app/Jobs/ExecuteStepJob.php` or `src/Laravel/Jobs/ExecuteStepJob.php` (We'll use `src/Laravel/Jobs/ExecuteStepJob.php`)
- Create: `src/Laravel/LaravelQueueDriver.php`
- Create: `tests/Unit/Laravel/LaravelQueueDriverTest.php`

- [ ] **Step 1: Write LaravelQueueDriverTest**

```php
<?php
namespace Tests\Unit\Laravel;

use Alama\LaravelArazzo\Laravel\LaravelQueueDriver;
use Alama\LaravelArazzo\Laravel\Jobs\ExecuteStepJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase; // Assuming Orchestra Testbench is set up

class LaravelQueueDriverTest extends TestCase
{
    public function test_dispatches_job(): void
    {
        Queue::fake();

        $driver = new LaravelQueueDriver();
        $driver->dispatchStep('wf_1', 'step_1', 10);

        Queue::assertPushed(ExecuteStepJob::class, function ($job) {
            return $job->workflowId === 'wf_1' && $job->stepId === 'step_1' && $job->delay === 10;
        });
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Laravel/LaravelQueueDriverTest.php`
Expected: FAIL

- [ ] **Step 3: Write minimal implementation**

Create `src/Laravel/Jobs/ExecuteStepJob.php`:
```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $workflowId,
        public string $stepId
    ) {}

    public function handle(\Alama\LaravelArazzo\Execution\StepExecutionWorker $worker): void
    {
        // For MVP, we need to load the Workflow DTO and Step DTO from DefinitionRegistry.
        // That wiring happens here. We will just leave it empty or mocked for this structural task.
    }
}
```

Create `src/Laravel/LaravelQueueDriver.php`:
```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Laravel\Jobs\ExecuteStepJob;

class LaravelQueueDriver implements QueueDriverInterface
{
    public function dispatchStep(string $workflowId, string $stepId, int $delaySeconds = 0): void
    {
        ExecuteStepJob::dispatch($workflowId, $stepId)->delay($delaySeconds);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Laravel/LaravelQueueDriverTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Laravel/Jobs/ExecuteStepJob.php src/Laravel/LaravelQueueDriver.php tests/Unit/Laravel/LaravelQueueDriverTest.php
git commit -m "feat: add Laravel queue bridge"
```
