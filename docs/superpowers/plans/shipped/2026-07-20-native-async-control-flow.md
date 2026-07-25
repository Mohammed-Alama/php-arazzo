# Native Asynchronous Control Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make retry/goto/end action semantics, AsyncAPI suspend/resume, and the diamond-DAG/queue gaps flagged in roadmap doc 03 actually work, on top of doc 02's persistence layer.

**Architecture:** A new `StepOutcomeHandler` owns all retry/goto/end decision logic, called by `StepExecutionWorker` (HTTP path) and a new `CorrelationResumer` (AsyncAPI resume path) after a step produces a result — no control-flow logic duplicated between the two entry points. A new `StepProtocolExecutorInterface` (`HttpStepExecutor`/`AsyncApiStepExecutor`) removes protocol branching from the worker. `StepExecutionWorker`'s lock is rekeyed to `executionId` and reloads persisted state before deciding what runs next, closing the diamond/fan-in lost-update race. `ExecuteStepJob`/`ResumeCorrelationJob` are plain framework-agnostic objects wrapped by real `ShouldQueue` Laravel jobs in `src/Laravel/Jobs/`.

**Tech Stack:** PHP 8.4, Laravel 11/12/13, Orchestra Testbench + Pest, sqlite for CI.

**Depends on:** [docs/superpowers/plans/2026-07-20-cqrs-event-sourced-persistence.md](2026-07-20-cqrs-event-sourced-persistence.md) must be fully implemented first — this plan's baseline is that plan's *target* `WorkflowContext` (with `workflowId`/`executionId`), `DefinitionRegistryInterface` (returns `ArazzoDocument`), `EventLedgerInterface`, `ExecutionRegistryInterface`, and the rewritten `StepExecutionWorker` from that plan's Task 13. Every "Modify" file below assumes that target shape is already on disk, not today's actual code.

**Design spec:** [docs/superpowers/specs/2026-07-20-native-async-control-flow-design.md](../specs/2026-07-20-native-async-control-flow-design.md)

## Global Constraints

- Framework-agnostic classes in `src/Execution/` must not import `Illuminate\Support\*` — matches the existing project boundary (see doc 02's plan). Laravel-specific wrapping (real `ShouldQueue` jobs, DB-backed registries) lives in `src/Laravel/`.
- Test paths mirror `src/` 1:1 (`tests/Execution`, `tests/Http/Controllers`, `tests/Laravel`, `tests/Laravel/Jobs`) — **not** the `tests/Unit`/`tests/Feature` split doc 02's plan used for its own new files. Where a task modifies a file doc 02's plan placed under `tests/Unit/*`, this plan's Step 1 relocates it with `git mv` first.
- Every file this plan touches must pass `vendor/bin/pest` and `vendor/bin/phpstan analyse` (level 8) before being considered done.
- Retry ceiling: `arazzo.max_retry_attempts` (config, default 10) caps retries regardless of a document's own `retryLimit` — a safety net independent of Arazzo semantics.
- Cross-workflow `goto`/`retry` targets only ever resolve within the *same* `ArazzoDocument` (`$document->workflows`) — jumping into a different document is out of scope.
- `PendingCorrelation` writes propagate failures (no swallow-and-log) — unlike `EventLedgerInterface`, silently losing a correlation strands the execution forever.

---

## Task 1: `StepStatus` enum + `WorkflowContext` status/attempts tracking

**Files:**
- Create: `src/Execution/StepStatus.php`
- Modify: `src/Execution/WorkflowContext.php`
- Test: `tests/Execution/WorkflowContextTest.php` (relocated from `tests/Unit/Execution/WorkflowContextTest.php`, which doc 02's plan creates)

**Interfaces:**
- Produces: `StepStatus` enum (`Pending|Succeeded|Failed|Retrying|Suspended`); `WorkflowContext::withStepStatus(string, StepStatus): self`, `getStepStatus(string): ?StepStatus`, `withStepAttemptIncremented(string): self`, `getStepAttempts(string): int`. Consumed by Task 2 (`DependencyAnalyzer`), Task 6-8 (`StepOutcomeHandler`), Task 13 (`StepExecutionWorker`).

- [ ] **Step 1: Relocate the doc-02 test file**

```bash
git mv tests/Unit/Execution/WorkflowContextTest.php tests/Execution/WorkflowContextTest.php
```

If that path doesn't exist (doc 02 was implemented with a different path), find it first: `find tests -iname WorkflowContextTest.php`, then `git mv` whatever you find to `tests/Execution/WorkflowContextTest.php`. Also update its namespace declaration from `Tests\Unit\Execution` to `Tests\Execution` — the rest of this step replaces the full file anyway so this only matters if you're diffing manually.

- [ ] **Step 2: Write the failing tests**

Replace the full contents of `tests/Execution/WorkflowContextTest.php` (keeps doc 02's 7 tests, adds 6 new ones):

```php
<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Execution\StepStatus;
use Alama\LaravelArazzo\Execution\WorkflowContext;

it('is immutable on withStepResult', function (): void {
    $context = new WorkflowContext('def_1', ['id' => 1]);
    $newContext = $context->withStepResult('step_1', ['success' => true]);

    expect($newContext)->not->toBe($context);
    expect($context->getSteps())->toBeEmpty();
    expect($newContext->getSteps()['step_1'])->toEqual(['success' => true]);
    expect($newContext->getDefinitionId())->toBe('def_1');
});

it('is immutable on withStepRequest and merges into steps', function (): void {
    $context = new WorkflowContext('def_1');
    $newContext = $context->withStepRequest('step_1', ['method' => 'GET', 'url' => 'http://x']);

    expect($newContext)->not->toBe($context);
    expect($context->getSteps())->toBeEmpty();
    expect($newContext->getSteps()['step_1']['request'])->toEqual(['method' => 'GET', 'url' => 'http://x']);
});

it('merges withStepResponse alongside an existing request', function (): void {
    $context = (new WorkflowContext('def_1'))
        ->withStepRequest('step_1', ['method' => 'GET'])
        ->withStepResponse('step_1', ['statusCode' => 200]);

    expect($context->getSteps()['step_1']['request'])->toEqual(['method' => 'GET']);
    expect($context->getSteps()['step_1']['response'])->toEqual(['statusCode' => 200]);
});

it('merges withStepOutput as individual keys', function (): void {
    $context = (new WorkflowContext('def_1'))
        ->withStepOutput('step_1', 'id', 123)
        ->withStepOutput('step_1', 'name', 'Alice');

    expect($context->getSteps()['step_1']['outputs'])->toEqual(['id' => 123, 'name' => 'Alice']);
});

it('defaults workflowId and executionId to null', function (): void {
    $context = new WorkflowContext('def_1');

    expect($context->getWorkflowId())->toBeNull();
    expect($context->getExecutionId())->toBeNull();
});

it('is immutable on withWorkflowId', function (): void {
    $context = new WorkflowContext('def_1');
    $newContext = $context->withWorkflowId('wf_1');

    expect($newContext)->not->toBe($context);
    expect($context->getWorkflowId())->toBeNull();
    expect($newContext->getWorkflowId())->toBe('wf_1');
});

it('is immutable on withExecutionId', function (): void {
    $context = new WorkflowContext('def_1');
    $newContext = $context->withExecutionId('exec_1');

    expect($newContext)->not->toBe($context);
    expect($context->getExecutionId())->toBeNull();
    expect($newContext->getExecutionId())->toBe('exec_1');
});

it('carries workflowId and executionId through every step mutator', function (): void {
    $context = (new WorkflowContext('def_1'))
        ->withWorkflowId('wf_1')
        ->withExecutionId('exec_1')
        ->withStepRequest('step_1', ['method' => 'GET'])
        ->withStepResponse('step_1', ['statusCode' => 200])
        ->withStepOutput('step_1', 'id', 1)
        ->withStepResult('step_2', ['done' => true]);

    expect($context->getWorkflowId())->toBe('wf_1');
    expect($context->getExecutionId())->toBe('exec_1');
});

it('defaults step status to null and attempts to 0', function (): void {
    $context = new WorkflowContext('def_1');

    expect($context->getStepStatus('step_1'))->toBeNull();
    expect($context->getStepAttempts('step_1'))->toBe(0);
});

it('is immutable on withStepStatus', function (): void {
    $context = new WorkflowContext('def_1');
    $newContext = $context->withStepStatus('step_1', StepStatus::Succeeded);

    expect($newContext)->not->toBe($context);
    expect($context->getStepStatus('step_1'))->toBeNull();
    expect($newContext->getStepStatus('step_1'))->toBe(StepStatus::Succeeded);
});

it('is immutable on withStepAttemptIncremented and increments from 0', function (): void {
    $context = new WorkflowContext('def_1');
    $once = $context->withStepAttemptIncremented('step_1');
    $twice = $once->withStepAttemptIncremented('step_1');

    expect($context->getStepAttempts('step_1'))->toBe(0);
    expect($once->getStepAttempts('step_1'))->toBe(1);
    expect($twice->getStepAttempts('step_1'))->toBe(2);
});

it('keeps status and attempts alongside request/response/outputs on the same step', function (): void {
    $context = (new WorkflowContext('def_1'))
        ->withStepRequest('step_1', ['method' => 'GET'])
        ->withStepStatus('step_1', StepStatus::Retrying)
        ->withStepAttemptIncremented('step_1');

    expect($context->getSteps()['step_1']['request'])->toEqual(['method' => 'GET']);
    expect($context->getStepStatus('step_1'))->toBe(StepStatus::Retrying);
    expect($context->getStepAttempts('step_1'))->toBe(1);
});

it('resets status and attempts for a step when withStepResult overwrites it', function (): void {
    $context = (new WorkflowContext('def_1'))
        ->withStepStatus('step_1', StepStatus::Retrying)
        ->withStepAttemptIncremented('step_1')
        ->withStepResult('step_1', ['statusCode' => 200]);

    expect($context->getStepStatus('step_1'))->toBeNull();
    expect($context->getStepAttempts('step_1'))->toBe(0);
});
```

- [ ] **Step 3: Run tests to verify the new ones fail**

Run: `vendor/bin/pest tests/Execution/WorkflowContextTest.php`
Expected: the 7 pre-existing tests PASS unchanged; the 6 new tests FAIL — `StepStatus` doesn't exist / `getStepStatus()`/`withStepStatus()`/`getStepAttempts()`/`withStepAttemptIncremented()` are undefined methods.

- [ ] **Step 4: Create `StepStatus`**

Create `src/Execution/StepStatus.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

enum StepStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Retrying = 'retrying';
    case Suspended = 'suspended';
}
```

- [ ] **Step 5: Extend `WorkflowContext`**

Replace the full contents of `src/Execution/WorkflowContext.php` (doc 02's target plus the 4 new methods):

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

final class WorkflowContext
{
    /**
     * @param array<string, mixed> $inputs
     * @param array<string, mixed> $steps
     * @param array<string, mixed> $components
     */
    public function __construct(
        private string $definitionId,
        private array $inputs = [],
        private array $steps = [],
        private array $components = [],
        private ?string $workflowId = null,
        private ?string $executionId = null,
    ) {
    }

    public function getDefinitionId(): string
    {
        return $this->definitionId;
    }

    public function getWorkflowId(): ?string
    {
        return $this->workflowId;
    }

    public function getExecutionId(): ?string
    {
        return $this->executionId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInputs(): array
    {
        return $this->inputs;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * @return array<string, mixed>
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    public function withWorkflowId(string $workflowId): self
    {
        return new self($this->definitionId, $this->inputs, $this->steps, $this->components, $workflowId, $this->executionId);
    }

    public function withExecutionId(string $executionId): self
    {
        return new self($this->definitionId, $this->inputs, $this->steps, $this->components, $this->workflowId, $executionId);
    }

    /**
     * @param array<string, mixed> $result
     */
    public function withStepResult(string $stepId, array $result): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId] = $result;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId);
    }

    /**
     * @param array<string, mixed> $request
     */
    public function withStepRequest(string $stepId, array $request): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['request'] = $request;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId);
    }

    /**
     * @param array<string, mixed> $response
     */
    public function withStepResponse(string $stepId, array $response): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['response'] = $response;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId);
    }

    public function withStepOutput(string $stepId, string $key, mixed $value): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['outputs'][$key] = $value;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId);
    }

    public function withStepStatus(string $stepId, StepStatus $status): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['status'] = $status->value;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId);
    }

    public function getStepStatus(string $stepId): ?StepStatus
    {
        $value = $this->steps[$stepId]['status'] ?? null;

        return $value !== null ? StepStatus::from($value) : null;
    }

    public function withStepAttemptIncremented(string $stepId): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['attempts'] = $this->getStepAttempts($stepId) + 1;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId);
    }

    public function getStepAttempts(string $stepId): int
    {
        return $this->steps[$stepId]['attempts'] ?? 0;
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/WorkflowContextTest.php`
Expected: PASS (13 tests)

- [ ] **Step 7: Commit**

```bash
git add src/Execution/StepStatus.php src/Execution/WorkflowContext.php tests/Execution/WorkflowContextTest.php
git commit -m "feat: add per-step status/attempts tracking to WorkflowContext"
```

---

## Task 2: `DependencyAnalyzer` — gate on status, not key existence

**Files:**
- Modify: `src/Execution/DependencyAnalyzer.php`
- Test: `tests/Execution/DependencyAnalyzerTest.php` (relocated from `tests/Unit/Execution/DependencyAnalyzerTest.php`)

**Interfaces:**
- Consumes: `WorkflowContext::getStepStatus()` (Task 1).
- Produces: `DependencyAnalyzer::getRunnableSteps()` now treats a step as "done" only when its status is `StepStatus::Succeeded` — a step at `Retrying`/`Suspended`/`Pending` (post-goto-reset) is neither runnable-again nor satisfies a downstream dependency. Consumed by Task 6-8 (`StepOutcomeHandler`), Task 13 (`StepExecutionWorker`).

- [ ] **Step 1: Relocate the existing test file**

```bash
git mv tests/Unit/Execution/DependencyAnalyzerTest.php tests/Execution/DependencyAnalyzerTest.php
```

- [ ] **Step 2: Write the failing tests**

Replace the full contents of `tests/Execution/DependencyAnalyzerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\StepStatus;
use Alama\LaravelArazzo\Execution\WorkflowContext;

it('finds runnable steps based on dependsOn', function (): void {
    $stepA = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
    $stepB = new Step('B', null, null, null, null, [], null, [], [], [], [], ['A']);

    $analyzer = new DependencyAnalyzer();

    $context = new WorkflowContext('def_1');
    $runnable = $analyzer->getRunnableSteps([$stepA, $stepB], $context);
    expect($runnable)->toHaveCount(1);
    expect($runnable[0]->stepId)->toBe('A');

    $context2 = $context->withStepResult('A', ['outputs' => []])->withStepStatus('A', StepStatus::Succeeded);
    $runnable2 = $analyzer->getRunnableSteps([$stepA, $stepB], $context2);
    expect($runnable2)->toHaveCount(1);
    expect($runnable2[0]->stepId)->toBe('B');
});

it('does not treat a Retrying step as complete or as runnable again', function (): void {
    $stepA = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
    $stepB = new Step('B', null, null, null, null, [], null, [], [], [], [], ['A']);
    $analyzer = new DependencyAnalyzer();

    $context = (new WorkflowContext('def_1'))
        ->withStepResult('A', ['statusCode' => 500])
        ->withStepStatus('A', StepStatus::Retrying);

    $runnable = $analyzer->getRunnableSteps([$stepA, $stepB], $context);

    expect($runnable)->toBeEmpty();
});

it('does not treat a Suspended step as complete', function (): void {
    $stepA = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
    $stepB = new Step('B', null, null, null, null, [], null, [], [], [], [], ['A']);
    $analyzer = new DependencyAnalyzer();

    $context = (new WorkflowContext('def_1'))->withStepStatus('A', StepStatus::Suspended);

    $runnable = $analyzer->getRunnableSteps([$stepA, $stepB], $context);

    expect($runnable)->toBeEmpty();
});

it('treats a goto-reset Pending step as runnable again even though a steps entry exists', function (): void {
    $stepA = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
    $analyzer = new DependencyAnalyzer();

    $context = (new WorkflowContext('def_1'))
        ->withStepResult('A', ['statusCode' => 200])
        ->withStepStatus('A', StepStatus::Succeeded)
        ->withStepStatus('A', StepStatus::Pending); // goto loop-back reset

    $runnable = $analyzer->getRunnableSteps([$stepA], $context);

    expect($runnable)->toHaveCount(1);
    expect($runnable[0]->stepId)->toBe('A');
});
```

- [ ] **Step 3: Run tests to verify the new ones fail**

Run: `vendor/bin/pest tests/Execution/DependencyAnalyzerTest.php`
Expected: the first test PASSes (unchanged behavior for the plain success case); the `Retrying`/`Suspended` tests FAIL because today's key-existence check treats any `steps[$stepId]` entry as complete, so `getRunnableSteps` wrongly returns `[]` for `[$stepA, $stepB]` in the "does not treat a Retrying step as complete" case too (both assertions happen to want `[]` there, so re-check by running — the meaningful failure is the 4th test, "goto-reset Pending", where today's key-existence check wrongly excludes A even though status is `Pending`).

- [ ] **Step 4: Implement the status-gated check**

Replace the full contents of `src/Execution/DependencyAnalyzer.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Step;

class DependencyAnalyzer
{
    /**
     * @param Step[] $allSteps
     *
     * @return Step[]
     */
    public function getRunnableSteps(array $allSteps, WorkflowContext $context): array
    {
        $runnable = [];

        foreach ($allSteps as $step) {
            if ($context->getStepStatus($step->stepId) === StepStatus::Succeeded) {
                continue;
            }

            if (empty($step->dependsOn)) {
                $runnable[] = $step;

                continue;
            }

            $dependenciesMet = true;
            foreach ($step->dependsOn as $dependencyId) {
                if ($context->getStepStatus($dependencyId) !== StepStatus::Succeeded) {
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

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/DependencyAnalyzerTest.php`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add src/Execution/DependencyAnalyzer.php tests/Execution/DependencyAnalyzerTest.php
git commit -m "fix: gate DependencyAnalyzer on step status, not key existence"
```

---

## Task 3: `ExecutionStatus` enum + `ExecutionRegistryInterface::complete()`

**Files:**
- Create: `src/Execution/ExecutionStatus.php`
- Modify: `src/Execution/Contracts/ExecutionRegistryInterface.php`
- Modify: `src/Laravel/DatabaseExecutionRegistry.php`
- Create: `database/migrations/add_status_to_arazzo_executions_table.php`
- Test: `tests/Laravel/DatabaseExecutionRegistryTest.php` (relocated from `tests/Unit/Laravel/DatabaseExecutionRegistryTest.php`, which doc 02's plan creates)

**Interfaces:**
- Produces: `ExecutionStatus` enum (`Running|Succeeded|Failed`); `ExecutionRegistryInterface::complete(string $executionId, ExecutionStatus $status): void`. Consumed by Task 6-8 (`StepOutcomeHandler`).

- [ ] **Step 1: Relocate the doc-02 test file**

```bash
git mv tests/Unit/Laravel/DatabaseExecutionRegistryTest.php tests/Laravel/DatabaseExecutionRegistryTest.php
```

If doc 02 placed it elsewhere, `find tests -iname DatabaseExecutionRegistryTest.php` first and move whatever you find. Update its namespace from `Tests\Unit\Laravel` to `Tests\Laravel` — Step 2 replaces the full file anyway.

- [ ] **Step 2: Write the failing tests**

Replace the full contents of `tests/Laravel/DatabaseExecutionRegistryTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Laravel;

use Alama\LaravelArazzo\Execution\ExecutionStatus;
use Alama\LaravelArazzo\Laravel\DatabaseExecutionRegistry;
use Alama\LaravelArazzo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(TestCase::class, RefreshDatabase::class);

function seedTestDefinitionRowForExecutionRegistry(): void
{
    DB::table('arazzo_definitions')->insert([
        'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        'document_identity' => 'Test',
        'content_hash' => str_repeat('a', 64),
        'raw_document' => json_encode(['x' => 1]),
        'created_at' => now(),
    ]);
}

it('inserts an execution row on start', function (): void {
    seedTestDefinitionRowForExecutionRegistry();

    $registry = new DatabaseExecutionRegistry(DB::connection());
    $registry->start('exec_1', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'wf_1');

    $this->assertDatabaseHas('arazzo_executions', [
        'id' => 'exec_1',
        'definition_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        'workflow_id' => 'wf_1',
        'status' => 'running',
    ]);
});

it('is idempotent across repeated start() calls', function (): void {
    seedTestDefinitionRowForExecutionRegistry();

    $registry = new DatabaseExecutionRegistry(DB::connection());
    $registry->start('exec_1', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'wf_1');
    $registry->start('exec_1', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'wf_1');

    expect(DB::table('arazzo_executions')->where('id', 'exec_1')->count())->toBe(1);
});

it('marks an execution succeeded and stamps completed_at', function (): void {
    seedTestDefinitionRowForExecutionRegistry();

    $registry = new DatabaseExecutionRegistry(DB::connection());
    $registry->start('exec_1', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'wf_1');
    $registry->complete('exec_1', ExecutionStatus::Succeeded);

    $row = DB::table('arazzo_executions')->where('id', 'exec_1')->first();
    expect($row->status)->toBe('succeeded');
    expect($row->completed_at)->not->toBeNull();
});

it('does not overwrite an already-completed execution', function (): void {
    seedTestDefinitionRowForExecutionRegistry();

    $registry = new DatabaseExecutionRegistry(DB::connection());
    $registry->start('exec_1', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'wf_1');
    $registry->complete('exec_1', ExecutionStatus::Succeeded);
    $registry->complete('exec_1', ExecutionStatus::Failed);

    $row = DB::table('arazzo_executions')->where('id', 'exec_1')->first();
    expect($row->status)->toBe('succeeded');
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Laravel/DatabaseExecutionRegistryTest.php`
Expected: the first 2 tests PASS unchanged; the `complete()` tests FAIL — no `status`/`completed_at` columns, no `complete()` method.

- [ ] **Step 4: Create `ExecutionStatus`**

Create `src/Execution/ExecutionStatus.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

enum ExecutionStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
```

- [ ] **Step 5: Extend `ExecutionRegistryInterface`**

Replace the full contents of `src/Execution/Contracts/ExecutionRegistryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Contracts;

use Alama\LaravelArazzo\Execution\ExecutionStatus;

interface ExecutionRegistryInterface
{
    public function start(string $executionId, string $definitionId, string $workflowId): void;

    public function complete(string $executionId, ExecutionStatus $status): void;
}
```

- [ ] **Step 6: Create the migration**

Create `database/migrations/add_status_to_arazzo_executions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arazzo_executions', function (Blueprint $table) {
            $table->string('status')->default('running')->after('workflow_id');
            $table->timestamp('completed_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('arazzo_executions', function (Blueprint $table) {
            $table->dropColumn(['status', 'completed_at']);
        });
    }
};
```

- [ ] **Step 7: Implement `DatabaseExecutionRegistry::complete()`**

Replace the full contents of `src/Laravel/DatabaseExecutionRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\ExecutionStatus;
use Illuminate\Database\ConnectionInterface;

class DatabaseExecutionRegistry implements ExecutionRegistryInterface
{
    public function __construct(
        private ConnectionInterface $db,
        private string $tableName = 'arazzo_executions',
    ) {
    }

    public function start(string $executionId, string $definitionId, string $workflowId): void
    {
        $this->db->table($this->tableName)->insertOrIgnore([
            'id' => $executionId,
            'definition_id' => $definitionId,
            'workflow_id' => $workflowId,
            'status' => ExecutionStatus::Running->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function complete(string $executionId, ExecutionStatus $status): void
    {
        $this->db->table($this->tableName)
            ->where('id', $executionId)
            ->where('status', ExecutionStatus::Running->value)
            ->update([
                'status' => $status->value,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Laravel/DatabaseExecutionRegistryTest.php`
Expected: PASS (4 tests) — note this requires the migration to be auto-discovered, which happens via doc 02's `hasMigrations()`/`runsMigrations()` wiring already in place.

- [ ] **Step 9: Commit**

```bash
git add src/Execution/ExecutionStatus.php src/Execution/Contracts/ExecutionRegistryInterface.php src/Laravel/DatabaseExecutionRegistry.php database/migrations/add_status_to_arazzo_executions_table.php tests/Laravel/DatabaseExecutionRegistryTest.php
git commit -m "feat: add ExecutionStatus and ExecutionRegistryInterface::complete()"
```

---

## Task 4: `ExpressionResolverInterface::evaluateCriteria()` — generalize the criteria evaluator

**Files:**
- Modify: `src/Execution/Contracts/ExpressionResolverInterface.php`
- Modify: `src/Execution/ArazzoExpressionResolver.php`
- Test: `tests/Execution/ArazzoExpressionResolverTest.php` (relocated from `tests/Unit/Execution/ArazzoExpressionResolverTest.php`)

**Interfaces:**
- Produces: `ExpressionResolverInterface::evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool` — the same evaluation logic `evaluateSuccessCriteria()` already has, generalized to take an arbitrary `list<SuccessCriterion>` (an action's own gating `criteria`, not just `$step->successCriteria`). `evaluateSuccessCriteria()` becomes a thin wrapper: `evaluateCriteria($step->successCriteria, $step, $context, $document)`. Consumed by Task 6-8 (`StepOutcomeHandler`, to evaluate each `onSuccess`/`onFailure` action's own `criteria`).
- Any other class implementing `ExpressionResolverInterface` (test doubles) must add this method or it won't compile — the two existing test doubles you'll touch in Task 13/18/19 are updated there.

- [ ] **Step 1: Relocate the existing test file**

```bash
git mv tests/Unit/Execution/ArazzoExpressionResolverTest.php tests/Execution/ArazzoExpressionResolverTest.php
```

Update its namespace from `Tests\Unit\Execution` to `Tests\Execution` and its class declaration; the rest of this task converts the file's remaining PHPUnit-class style to Pest as part of extending it (matches the project's dominant Pest convention, same reasoning doc 02 used for `WorkflowContextTest.php`).

- [ ] **Step 2: Write the failing test**

Replace the full contents of `tests/Execution/ArazzoExpressionResolverTest.php` (converts the 7 existing PHPUnit-class tests to Pest `it(...)`, unchanged behavior, plus 1 new test for the generalized method):

```php
<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\CriterionType;
use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\PayloadReplacement;
use Alama\LaravelArazzo\Dto\RequestBody;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\SuccessCriterion;
use Alama\LaravelArazzo\Execution\ArazzoExpressionResolver;
use Alama\LaravelArazzo\Execution\Exceptions\UnsupportedCriterionTypeException;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Resolution\DefaultSourceResolver;
use Alama\LaravelArazzo\Resolution\Fetchers\LocalFetcher;
use Alama\LaravelArazzo\Resolution\Parsers\OpenApiSourceParser;
use GuzzleHttp\Psr7\HttpFactory;

function arazzoResolverOpenApiFile(): string
{
    static $path = null;
    if ($path !== null) {
        return $path;
    }

    $openApiJson = json_encode([
        'openapi' => '3.0.0',
        'info' => ['title' => 'Test', 'version' => '1.0'],
        'servers' => [['url' => 'https://api.test']],
        'paths' => [
            '/users' => [
                'post' => [
                    'operationId' => 'createUser',
                    'parameters' => [
                        ['name' => 'dryRun', 'in' => 'query', 'schema' => ['type' => 'boolean']],
                    ],
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => ['age' => ['type' => 'integer']],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Created',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => ['id' => ['type' => 'integer']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $path = tempnam(sys_get_temp_dir(), 'openapi_') . '.json';
    file_put_contents($path, $openApiJson);

    return $path;
}

function arazzoResolver(): ArazzoExpressionResolver
{
    $sourceResolver = new DefaultSourceResolver(
        fetchers: ['file' => new LocalFetcher()],
        parsers: [SourceType::Openapi->value => new OpenApiSourceParser()],
    );

    return new ArazzoExpressionResolver($sourceResolver, new HttpFactory(), new ExpressionEvaluator());
}

function arazzoResolverDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.1',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [new SourceDescription('test-api', arazzoResolverOpenApiFile(), SourceType::Openapi)],
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

it('compiles request with resolved operation and cast query param', function (): void {
    $resolver = arazzoResolver();
    $document = arazzoResolverDocument();

    $step = new Step(
        stepId: 'create-user',
        description: null,
        operationId: 'createUser',
        operationPath: null,
        workflowId: null,
        parameters: [new Parameter('dryRun', ParameterIn::Query, 'true')],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $request = $resolver->compileRequest($step, new WorkflowContext('def_1'), $document);

    expect($request->getMethod())->toBe('POST');
    expect((string) $request->getUri())->toBe('https://api.test/users?dryRun=1');
});

it('compiles request body with schema cast replacement', function (): void {
    $resolver = arazzoResolver();
    $document = arazzoResolverDocument();

    $step = new Step(
        stepId: 'create-user',
        description: null,
        operationId: 'createUser',
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: new RequestBody('application/json', ['age' => null], [
            new PayloadReplacement('/age', new Expression('{$inputs.age}')),
        ]),
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = new WorkflowContext('def_1', ['age' => '30']);

    $request = $resolver->compileRequest($step, $context, $document);

    expect(json_decode((string) $request->getBody(), true))->toBe(['age' => 30]);
});

it('falls back to literal url without a document', function (): void {
    $resolver = arazzoResolver();

    $step = new Step('step1', null, null, 'http://api.example.com/users', null, [], null, [], [], [], []);
    $context = new WorkflowContext('def_1');

    $request = $resolver->compileRequest($step, $context);

    expect($request->getMethod())->toBe('GET');
    expect((string) $request->getUri())->toBe('http://api.example.com/users');
});

it('extracts output via runtime expression with schema cast', function (): void {
    $resolver = arazzoResolver();
    $document = arazzoResolverDocument();

    $step = new Step(
        stepId: 'create-user',
        description: null,
        operationId: 'createUser',
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: ['userId' => new Expression('{$steps.create-user.response.body#/id}')],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('create-user', [
        'statusCode' => 201,
        'headers' => [],
        'body' => ['id' => '123'],
    ]);

    $outputs = $resolver->extractOutputs($step, $context, $document);

    expect($outputs['userId'])->toBe(123);
});

it('extracts output via bare jsonpath', function (): void {
    $resolver = arazzoResolver();

    $step = new Step(
        stepId: 'step1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: ['firstId' => new Expression('$.users[0].id')],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', [
        'statusCode' => 200,
        'headers' => [],
        'body' => ['users' => [['id' => 1], ['id' => 2]]],
    ]);

    $outputs = $resolver->extractOutputs($step, $context);

    expect($outputs['firstId'])->toBe(1);
});

it('evaluates success criteria: simple, regex, jsonpath', function (): void {
    $resolver = arazzoResolver();

    $step = new Step(
        stepId: 'step1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [
            new SuccessCriterion(null, '{$statusCode} == 200', CriterionType::Simple),
            new SuccessCriterion('{$statusCode}', '^20[0-1]$', CriterionType::Regex),
            new SuccessCriterion(null, '$.users[?(@.id==1)]', CriterionType::JsonPath),
        ],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))
        ->withStepRequest('step1', [])
        ->withStepResponse('step1', [
            'statusCode' => 200,
            'headers' => [],
            'body' => ['users' => [['id' => 1], ['id' => 2]]],
        ]);

    expect($resolver->evaluateSuccessCriteria($step, $context))->toBeTrue();
});

it('throws for an unsupported criterion type', function (): void {
    $resolver = arazzoResolver();

    $step = new Step(
        stepId: 'step1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [new SuccessCriterion(null, '/users/id', CriterionType::XPath)],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', []);

    expect(fn () => $resolver->evaluateSuccessCriteria($step, $context))
        ->toThrow(UnsupportedCriterionTypeException::class);
});

it('evaluateCriteria evaluates an arbitrary criteria list against the current step response, independent of successCriteria', function (): void {
    $resolver = arazzoResolver();

    $step = new Step(
        stepId: 'step1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [], // deliberately empty -- action-level criteria are independent
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', [
        'statusCode' => 500,
        'headers' => [],
        'body' => [],
    ]);

    $matches = $resolver->evaluateCriteria(
        [new SuccessCriterion('{$statusCode}', '^5\d\d$', CriterionType::Regex)],
        $step,
        $context,
    );
    $noMatch = $resolver->evaluateCriteria(
        [new SuccessCriterion('{$statusCode}', '^2\d\d$', CriterionType::Regex)],
        $step,
        $context,
    );

    expect($matches)->toBeTrue();
    expect($noMatch)->toBeFalse();
});

it('evaluateCriteria returns true for an empty criteria list', function (): void {
    $resolver = arazzoResolver();
    $step = new Step('step1', null, null, null, null, [], null, [], [], [], []);
    $context = new WorkflowContext('def_1');

    expect($resolver->evaluateCriteria([], $step, $context))->toBeTrue();
});
```

- [ ] **Step 3: Run tests to verify the new ones fail**

Run: `vendor/bin/pest tests/Execution/ArazzoExpressionResolverTest.php`
Expected: the 7 converted tests PASS unchanged; the 2 `evaluateCriteria` tests FAIL — `evaluateCriteria()` is not defined on `ArazzoExpressionResolver`.

- [ ] **Step 4: Update the interface**

Replace the full contents of `src/Execution/Contracts/ExpressionResolverInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Contracts;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Psr\Http\Message\RequestInterface;

interface ExpressionResolverInterface
{
    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface;

    /**
     * @return array<string, mixed>
     */
    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array;

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool;

    /**
     * @param list<\Alama\LaravelArazzo\Dto\SuccessCriterion> $criteria
     */
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool;
}
```

- [ ] **Step 5: Implement it in `ArazzoExpressionResolver`**

In `src/Execution/ArazzoExpressionResolver.php`, replace the `evaluateSuccessCriteria` method with:

```php
    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return $this->evaluateCriteria($step->successCriteria, $step, $context, $document);
    }

    /**
     * @param list<SuccessCriterion> $criteria
     */
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        if (empty($criteria)) {
            return true;
        }

        $responseBody = $context->getSteps()[$step->stepId]['response']['body'] ?? [];

        foreach ($criteria as $criterion) {
            $type = $criterion->type ?? CriterionType::Simple;

            if ($type === CriterionType::Simple) {
                // Not fully implemented evaluating logic yet, just returning true for now
                // Needs a real expression parser for boolean logic
                continue;
            }

            if ($type === CriterionType::Regex) {
                if ($criterion->context === null) {
                    continue; // Skip invalid regex criteria
                }
                $target = $this->evaluator->evaluate(new Expression($criterion->context), $context, $step->stepId);
                if (!preg_match('/' . str_replace('/', '\/', $criterion->condition) . '/', (string) $target)) {
                    return false;
                }

                continue;
            }

            if ($type === CriterionType::JsonPath) {
                $result = JsonPathEvaluator::evaluate($criterion->condition, is_array($responseBody) ? $responseBody : []);
                if (empty($result)) {
                    return false;
                }

                continue;
            }

            throw new UnsupportedCriterionTypeException("Criterion type '{$type->value}' is not supported.");
        }

        return true;
    }
```

This is the exact same loop body `evaluateSuccessCriteria` had, moved verbatim into the generalized method — no behavior change for the existing `evaluateSuccessCriteria` callers, since it now just forwards `$step->successCriteria`.

Add `use Alama\LaravelArazzo\Dto\SuccessCriterion;` to the file's imports alongside the existing ones.

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/ArazzoExpressionResolverTest.php`
Expected: PASS (9 tests)

- [ ] **Step 7: Commit**

```bash
git add src/Execution/Contracts/ExpressionResolverInterface.php src/Execution/ArazzoExpressionResolver.php tests/Execution/ArazzoExpressionResolverTest.php
git commit -m "feat: generalize success-criteria evaluation into evaluateCriteria()"
```

---

## Task 5: `PendingCorrelation` + `PendingCorrelationRegistryInterface` + `DatabasePendingCorrelationRegistry`

**Files:**
- Create: `src/Execution/PendingCorrelation.php`
- Create: `src/Execution/Contracts/PendingCorrelationRegistryInterface.php`
- Create: `src/Laravel/DatabasePendingCorrelationRegistry.php`
- Create: `database/migrations/create_arazzo_pending_correlations_table.php`
- Test: `tests/Laravel/DatabasePendingCorrelationRegistryTest.php`

**Interfaces:**
- Produces: `PendingCorrelation` (readonly value object: `correlationId`, `executionId`, `stepId`, `channelPath`); `PendingCorrelationRegistryInterface` with `create()`, `findByCorrelationId()`, `consume()`, `existsForExecution()`. Consumed by Task 12 (`AsyncApiStepExecutor`), Task 6-8 (`StepOutcomeHandler`), Task 15 (`CorrelationResumer`), Task 17 (`WebhookResumeController`).
- Writes propagate failures (no swallow-and-log) — see Global Constraints.

- [ ] **Step 1: Write the failing tests**

Create `tests/Laravel/DatabasePendingCorrelationRegistryTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Laravel;

use Alama\LaravelArazzo\Laravel\DatabasePendingCorrelationRegistry;
use Alama\LaravelArazzo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(TestCase::class, RefreshDatabase::class);

it('creates and finds a pending correlation', function (): void {
    $registry = new DatabasePendingCorrelationRegistry(DB::connection());

    $registry->create('corr_1', 'exec_1', 'step_1', 'channels/rides/created');

    $found = $registry->findByCorrelationId('corr_1');

    expect($found)->not->toBeNull();
    expect($found->correlationId)->toBe('corr_1');
    expect($found->executionId)->toBe('exec_1');
    expect($found->stepId)->toBe('step_1');
    expect($found->channelPath)->toBe('channels/rides/created');
});

it('returns null for an unknown correlation id', function (): void {
    $registry = new DatabasePendingCorrelationRegistry(DB::connection());

    expect($registry->findByCorrelationId('missing'))->toBeNull();
});

it('consume deletes the row so a second lookup returns null', function (): void {
    $registry = new DatabasePendingCorrelationRegistry(DB::connection());
    $registry->create('corr_1', 'exec_1', 'step_1', 'channels/rides/created');

    $registry->consume('corr_1');

    expect($registry->findByCorrelationId('corr_1'))->toBeNull();
});

it('existsForExecution reflects whether any correlation is outstanding', function (): void {
    $registry = new DatabasePendingCorrelationRegistry(DB::connection());

    expect($registry->existsForExecution('exec_1'))->toBeFalse();

    $registry->create('corr_1', 'exec_1', 'step_1', 'channels/rides/created');
    expect($registry->existsForExecution('exec_1'))->toBeTrue();

    $registry->consume('corr_1');
    expect($registry->existsForExecution('exec_1'))->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Laravel/DatabasePendingCorrelationRegistryTest.php`
Expected: FAIL — `DatabasePendingCorrelationRegistry` doesn't exist, `arazzo_pending_correlations` table doesn't exist.

- [ ] **Step 3: Create `PendingCorrelation`**

Create `src/Execution/PendingCorrelation.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

final readonly class PendingCorrelation
{
    public function __construct(
        public string $correlationId,
        public string $executionId,
        public string $stepId,
        public string $channelPath,
    ) {
    }
}
```

- [ ] **Step 4: Create the interface**

Create `src/Execution/Contracts/PendingCorrelationRegistryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Contracts;

use Alama\LaravelArazzo\Execution\PendingCorrelation;

interface PendingCorrelationRegistryInterface
{
    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void;

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation;

    public function consume(string $correlationId): void;

    public function existsForExecution(string $executionId): bool;
}
```

- [ ] **Step 5: Create the migration**

Create `database/migrations/create_arazzo_pending_correlations_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arazzo_pending_correlations', function (Blueprint $table) {
            $table->id();
            $table->string('correlation_id')->unique();
            $table->ulid('execution_id')->index();
            $table->string('step_id');
            $table->string('channel_path');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arazzo_pending_correlations');
    }
};
```

- [ ] **Step 6: Implement `DatabasePendingCorrelationRegistry`**

Create `src/Laravel/DatabasePendingCorrelationRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\PendingCorrelation;
use Illuminate\Database\ConnectionInterface;

class DatabasePendingCorrelationRegistry implements PendingCorrelationRegistryInterface
{
    public function __construct(
        private ConnectionInterface $db,
        private string $tableName = 'arazzo_pending_correlations',
    ) {
    }

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void
    {
        $this->db->table($this->tableName)->insert([
            'correlation_id' => $correlationId,
            'execution_id' => $executionId,
            'step_id' => $stepId,
            'channel_path' => $channelPath,
            'created_at' => now(),
        ]);
    }

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        $row = $this->db->table($this->tableName)->where('correlation_id', $correlationId)->first();
        if ($row === null) {
            return null;
        }

        return new PendingCorrelation(
            (string) $row->correlation_id,
            (string) $row->execution_id,
            (string) $row->step_id,
            (string) $row->channel_path,
        );
    }

    public function consume(string $correlationId): void
    {
        $this->db->table($this->tableName)->where('correlation_id', $correlationId)->delete();
    }

    public function existsForExecution(string $executionId): bool
    {
        return $this->db->table($this->tableName)->where('execution_id', $executionId)->exists();
    }
}
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Laravel/DatabasePendingCorrelationRegistryTest.php`
Expected: PASS (4 tests) — this migration is auto-discovered once Task 16 adds it to the `hasMigrations()` list; if it's not yet wired when you run this task, note it and re-verify after Task 16 (same pattern doc 02's plan used for its own migrations).

- [ ] **Step 8: Commit**

```bash
git add src/Execution/PendingCorrelation.php src/Execution/Contracts/PendingCorrelationRegistryInterface.php src/Laravel/DatabasePendingCorrelationRegistry.php database/migrations/create_arazzo_pending_correlations_table.php tests/Laravel/DatabasePendingCorrelationRegistryTest.php
git commit -m "feat: add PendingCorrelationRegistryInterface/DatabasePendingCorrelationRegistry"
```

---

## Task 6: `StepOutcomeHandler` — retry handling (first pass)

**Files:**
- Create: `src/Execution/Exceptions/GotoTargetNotFoundException.php`
- Create: `src/Execution/StepOutcomeHandler.php`
- Test: `tests/Execution/StepOutcomeHandlerTest.php`

**Interfaces:**
- Consumes: `WorkflowContext` status/attempts (Task 1), `ExecutionRegistryInterface::complete()` (Task 3), `ExpressionResolverInterface::evaluateCriteria()` (Task 4), `Engine::evaluate()` (existing), `QueueDriverInterface::dispatch()` (existing, delay-aware).
- Produces: `StepOutcomeHandler::handle(ArazzoDocument $document, Workflow $workflow, Step $step, WorkflowContext $context, string $executionId, bool $criteriaMet): void`. This task implements the `RetryAction` branch and the "no matching action" implicit continue/terminal-fail branches; `SuccessGotoAction`/`FailureGotoAction`/`SuccessEndAction`/`FailureEndAction` throw `LogicException('Unhandled action type: ...')` until Tasks 7-8 replace that branch — not exercised by this task's tests. Consumed by Task 13 (`StepExecutionWorker`), Task 15 (`CorrelationResumer`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Execution/StepOutcomeHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Dto\Action\FailureGotoAction;
use Alama\LaravelArazzo\Dto\Action\RetryAction;
use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Reusable;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\SuccessCriterion;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\ExecutionStatus;
use Alama\LaravelArazzo\Execution\StepOutcomeHandler;
use Alama\LaravelArazzo\Execution\StepStatus;
use Alama\LaravelArazzo\Execution\SyncQueueDriver;
use Alama\LaravelArazzo\Execution\WorkflowContext;

class StepOutcomeMockExecutionRegistry implements ExecutionRegistryInterface
{
    /** @var list<array{executionId: string, status: ExecutionStatus}> */
    public array $completed = [];

    public function start(string $executionId, string $definitionId, string $workflowId): void
    {
    }

    public function complete(string $executionId, ExecutionStatus $status): void
    {
        $this->completed[] = ['executionId' => $executionId, 'status' => $status];
    }
}

class StepOutcomeMockEventLedger implements EventLedgerInterface
{
    /** @var list<array{executionId: string, eventType: string, payload: array<string, mixed>}> */
    public array $appended = [];

    public function append(string $executionId, string $eventType, array $payload): void
    {
        $this->appended[] = ['executionId' => $executionId, 'eventType' => $eventType, 'payload' => $payload];
    }
}

class StepOutcomeMockExpressionResolver implements ExpressionResolverInterface
{
    public function compileRequest(\Alama\LaravelArazzo\Dto\Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): \Psr\Http\Message\RequestInterface
    {
        throw new \LogicException('not used by StepOutcomeHandler tests');
    }

    public function extractOutputs(\Alama\LaravelArazzo\Dto\Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        return [];
    }

    public function evaluateSuccessCriteria(\Alama\LaravelArazzo\Dto\Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    // Test convention: an empty criteria list always matches (unconditional action); a
    // non-empty list matches only when its first criterion's condition is the literal
    // string 'MATCH'. Keeps these tests independent of the real criterion evaluator.
    public function evaluateCriteria(array $criteria, \Alama\LaravelArazzo\Dto\Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        if ($criteria === []) {
            return true;
        }

        return $criteria[0]->condition === 'MATCH';
    }
}

function stepOutcomeStep(string $id, array $onFailure = [], array $onSuccess = [], array $dependsOn = []): Step
{
    return new Step($id, null, null, null, null, [], null, [], $onSuccess, $onFailure, [], $dependsOn);
}

function stepOutcomeWorkflow(string $id, array $steps, array $failureActions = [], array $successActions = []): Workflow
{
    return new Workflow($id, null, null, null, [], $steps, $successActions, $failureActions, [], []);
}

function stepOutcomeDocument(array $workflows, ?Components $components = null): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: $workflows,
        components: $components ?? new Components([], [], [], []),
        specificationExtensions: [],
    );
}

/** @return array{0: StepOutcomeHandler, 1: SyncQueueDriver, 2: StepOutcomeMockExecutionRegistry, 3: StepOutcomeMockEventLedger} */
function makeStepOutcomeHandler(int $maxRetryAttempts = 10): array
{
    $queue = new SyncQueueDriver();
    $executionRegistry = new StepOutcomeMockExecutionRegistry();
    $eventLedger = new StepOutcomeMockEventLedger();
    $engine = new Engine(new DependencyAnalyzer(), $queue, new class implements \Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface {
        public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
        {
        }

        public function load(string $executionId): ?array
        {
            return null;
        }
    });
    $resolver = new StepOutcomeMockExpressionResolver();

    $handler = new StepOutcomeHandler($queue, $engine, $executionRegistry, $eventLedger, $resolver, $maxRetryAttempts);

    return [$handler, $queue, $executionRegistry, $eventLedger];
}

it('continues normally and dispatches the next runnable step when criteria met and no actions match', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler();

    $stepA = stepOutcomeStep('A');
    $stepB = stepOutcomeStep('B', dependsOn: ['A']);
    $workflow = stepOutcomeWorkflow('wf_1', [$stepA, $stepB]);
    $document = stepOutcomeDocument([$workflow]);

    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $stepA, $context, 'exec_1', true);

    expect($queue->dispatched)->toHaveCount(1);
    expect($queue->dispatched[0]['job']->step->stepId)->toBe('B');
});

it('terminates the execution as failed when criteria not met and no failure action matches', function (): void {
    [$handler, , $executionRegistry, $eventLedger] = makeStepOutcomeHandler();

    $step = stepOutcomeStep('A');
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', false);

    expect($executionRegistry->completed)->toHaveCount(1);
    expect($executionRegistry->completed[0]['status'])->toBe(ExecutionStatus::Failed);
    expect($eventLedger->appended[0]['eventType'])->toBe('execution.failed');
});

it('retries the same step with the configured delay and increments attempts', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler();

    $retry = new RetryAction('retry-1', retryAfter: 30, retryLimit: 3, stepId: null, workflowId: null, criteria: []);
    $step = stepOutcomeStep('A', onFailure: [$retry]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', false);

    expect($queue->dispatched)->toHaveCount(1);
    expect($queue->dispatched[0]['delaySeconds'])->toBe(30);
    $dispatchedJob = $queue->dispatched[0]['job'];
    expect($dispatchedJob->step->stepId)->toBe('A');
    expect($dispatchedJob->context->getStepAttempts('A'))->toBe(1);
    expect($dispatchedJob->context->getStepStatus('A'))->toBe(StepStatus::Retrying);
});

it('falls through to the next onFailure action once the retry limit is exhausted', function (): void {
    [$handler, $queue, $executionRegistry, $eventLedger] = makeStepOutcomeHandler();

    $retry = new RetryAction('retry-1', retryAfter: 0, retryLimit: 1, stepId: null, workflowId: null, criteria: []);
    $step = stepOutcomeStep('A', onFailure: [$retry]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);

    // Simulate this being the step's 2nd failure -- attempts already at the limit (1).
    $context = (new WorkflowContext('def_1'))
        ->withWorkflowId('wf_1')
        ->withExecutionId('exec_1')
        ->withStepAttemptIncremented('A');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', false);

    expect($queue->dispatched)->toBeEmpty();
    expect($eventLedger->appended[0]['eventType'])->toBe('step.retry_exhausted');
    expect($executionRegistry->completed[0]['status'])->toBe(ExecutionStatus::Failed);
});

it('honors a config-level retry ceiling even when the document retryLimit is higher', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler(maxRetryAttempts: 2);

    $retry = new RetryAction('retry-1', retryAfter: 0, retryLimit: 100, stepId: null, workflowId: null, criteria: []);
    $step = stepOutcomeStep('A', onFailure: [$retry]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);

    $context = (new WorkflowContext('def_1'))
        ->withWorkflowId('wf_1')
        ->withExecutionId('exec_1')
        ->withStepAttemptIncremented('A')
        ->withStepAttemptIncremented('A');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', false);

    expect($queue->dispatched)->toBeEmpty();
});

it('retries into a different target step, marking it Pending', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler();

    $retry = new RetryAction('retry-1', retryAfter: 5, retryLimit: 3, stepId: 'B', workflowId: null, criteria: []);
    $stepA = stepOutcomeStep('A', onFailure: [$retry]);
    $stepB = stepOutcomeStep('B');
    $workflow = stepOutcomeWorkflow('wf_1', [$stepA, $stepB]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $stepA, $context, 'exec_1', false);

    $dispatchedJob = $queue->dispatched[0]['job'];
    expect($dispatchedJob->step->stepId)->toBe('B');
    expect($dispatchedJob->context->getStepStatus('B'))->toBe(StepStatus::Pending);
});

it('resolves a Reusable failure action from components before matching', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler();

    $retry = new RetryAction('shared-retry', retryAfter: 10, retryLimit: 3, stepId: null, workflowId: null, criteria: []);
    $components = new Components([], [], [], ['sharedRetry' => $retry]);
    $reusable = new Reusable('$components.failureActions.sharedRetry');

    $step = stepOutcomeStep('A', onFailure: [$reusable]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow], $components);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', false);

    expect($queue->dispatched)->toHaveCount(1);
    expect($queue->dispatched[0]['delaySeconds'])->toBe(10);
});

it('only matches an action whose own criteria evaluate true, skipping ones that do not', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler();

    $skippedRetry = new RetryAction('no-match', retryAfter: 1, retryLimit: 3, stepId: null, workflowId: null, criteria: [
        new SuccessCriterion(null, 'NO_MATCH', null),
    ]);
    $matchedRetry = new RetryAction('matches', retryAfter: 2, retryLimit: 3, stepId: null, workflowId: null, criteria: [
        new SuccessCriterion(null, 'MATCH', null),
    ]);
    $step = stepOutcomeStep('A', onFailure: [$skippedRetry, $matchedRetry]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', false);

    expect($queue->dispatched[0]['delaySeconds'])->toBe(2);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/StepOutcomeHandlerTest.php`
Expected: FAIL — `StepOutcomeHandler` doesn't exist.

- [ ] **Step 3: Create `GotoTargetNotFoundException`**

Create `src/Execution/Exceptions/GotoTargetNotFoundException.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Exceptions;

use RuntimeException;

final class GotoTargetNotFoundException extends RuntimeException
{
}
```

- [ ] **Step 4: Implement `StepOutcomeHandler`**

Create `src/Execution/StepOutcomeHandler.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Action\FailureAction;
use Alama\LaravelArazzo\Dto\Action\RetryAction;
use Alama\LaravelArazzo\Dto\Action\SuccessAction;
use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Reusable;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Exceptions\GotoTargetNotFoundException;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use LogicException;

class StepOutcomeHandler
{
    public function __construct(
        private QueueDriverInterface $queueDriver,
        private Engine $engine,
        private ExecutionRegistryInterface $executionRegistry,
        private EventLedgerInterface $eventLedger,
        private ExpressionResolverInterface $expressionResolver,
        private int $maxRetryAttempts = 10,
    ) {
    }

    public function handle(
        ArazzoDocument $document,
        Workflow $workflow,
        Step $step,
        WorkflowContext $context,
        string $executionId,
        bool $criteriaMet,
    ): void {
        $actions = $this->resolveActionList($document, $workflow, $step, $criteriaMet);

        $this->applyFirstMatch($actions, $document, $workflow, $step, $context, $executionId, $criteriaMet);
    }

    /**
     * @param list<SuccessAction|FailureAction> $actions
     */
    private function applyFirstMatch(
        array $actions,
        ArazzoDocument $document,
        Workflow $workflow,
        Step $step,
        WorkflowContext $context,
        string $executionId,
        bool $criteriaMet,
    ): void {
        $matched = $this->firstMatchingAction($actions, $step, $context, $document);

        if ($matched === null) {
            if ($criteriaMet) {
                $this->continueNormally($workflow, $step, $context, $executionId);
            } else {
                $this->terminate($context, $executionId, ExecutionStatus::Failed, 'execution.failed');
            }

            return;
        }

        if ($matched instanceof RetryAction) {
            $this->handleRetry($matched, $actions, $document, $workflow, $step, $context, $executionId, $criteriaMet);

            return;
        }

        throw new LogicException('Unhandled action type: ' . $matched::class);
    }

    /**
     * @return list<SuccessAction|FailureAction>
     */
    private function resolveActionList(ArazzoDocument $document, Workflow $workflow, Step $step, bool $criteriaMet): array
    {
        $stepList = $criteriaMet ? $step->onSuccess : $step->onFailure;
        $list = $stepList !== [] ? $stepList : ($criteriaMet ? $workflow->successActions : $workflow->failureActions);
        $componentType = $criteriaMet ? 'successActions' : 'failureActions';

        return array_map(fn ($action) => $this->resolveReusable($action, $document, $componentType), $list);
    }

    private function resolveReusable(SuccessAction|FailureAction|Reusable $action, ArazzoDocument $document, string $componentType): SuccessAction|FailureAction
    {
        if (!$action instanceof Reusable) {
            return $action;
        }

        $prefix = "\$components.{$componentType}.";
        if (!str_starts_with($action->reference, $prefix)) {
            throw new GotoTargetNotFoundException("Reusable reference '{$action->reference}' does not target components.{$componentType}.");
        }

        $name = substr($action->reference, strlen($prefix));
        $resolved = $componentType === 'successActions'
            ? ($document->components->successActions[$name] ?? null)
            : ($document->components->failureActions[$name] ?? null);

        if ($resolved === null) {
            throw new GotoTargetNotFoundException("Reusable reference '{$action->reference}' does not resolve.");
        }

        return $resolved;
    }

    /**
     * @param list<SuccessAction|FailureAction> $actions
     */
    private function firstMatchingAction(array $actions, Step $step, WorkflowContext $context, ArazzoDocument $document): SuccessAction|FailureAction|null
    {
        foreach ($actions as $action) {
            if ($this->expressionResolver->evaluateCriteria($action->criteria, $step, $context, $document)) {
                return $action;
            }
        }

        return null;
    }

    /**
     * @param list<SuccessAction|FailureAction> $actionsConsidered
     */
    private function handleRetry(
        RetryAction $action,
        array $actionsConsidered,
        ArazzoDocument $document,
        Workflow $workflow,
        Step $step,
        WorkflowContext $context,
        string $executionId,
        bool $criteriaMet,
    ): void {
        $attempts = $context->getStepAttempts($step->stepId);
        $limit = min($action->retryLimit ?? PHP_INT_MAX, $this->maxRetryAttempts);

        if ($attempts >= $limit) {
            $this->eventLedger->append($executionId, 'step.retry_exhausted', [
                'stepId' => $step->stepId,
                'attempts' => $attempts,
            ]);

            $remaining = array_values(array_filter($actionsConsidered, static fn ($a) => $a !== $action));
            $this->applyFirstMatch($remaining, $document, $workflow, $step, $context, $executionId, $criteriaMet);

            return;
        }

        $targetStepId = $action->stepId ?? $step->stepId;
        $targetWorkflowId = $action->workflowId ?? $workflow->workflowId;
        [$targetWorkflow, $targetStep] = $this->resolveTarget($document, $targetWorkflowId, $targetStepId);

        $newContext = $context
            ->withStepAttemptIncremented($step->stepId)
            ->withStepStatus($step->stepId, StepStatus::Retrying);

        if ($targetWorkflow->workflowId !== $context->getWorkflowId()) {
            $newContext = $newContext->withWorkflowId($targetWorkflow->workflowId);
        }
        if ($targetStep->stepId !== $step->stepId) {
            $newContext = $newContext->withStepStatus($targetStep->stepId, StepStatus::Pending);
        }

        $this->queueDriver->dispatch(new ExecuteStepJob($targetStep, $newContext), $action->retryAfter ?? 0);
    }

    /**
     * @return array{0: Workflow, 1: Step}
     */
    private function resolveTarget(ArazzoDocument $document, string $workflowId, string $stepId): array
    {
        $workflow = $this->findWorkflow($document, $workflowId);
        if ($workflow === null) {
            throw new GotoTargetNotFoundException("Action references unknown workflowId '{$workflowId}'.");
        }

        $step = $this->findStep($workflow, $stepId);
        if ($step === null) {
            throw new GotoTargetNotFoundException("Action references unknown stepId '{$stepId}' in workflow '{$workflow->workflowId}'.");
        }

        return [$workflow, $step];
    }

    private function findWorkflow(ArazzoDocument $document, string $workflowId): ?Workflow
    {
        foreach ($document->workflows as $workflow) {
            if ($workflow->workflowId === $workflowId) {
                return $workflow;
            }
        }

        return null;
    }

    private function findStep(Workflow $workflow, string $stepId): ?Step
    {
        foreach ($workflow->steps as $step) {
            if ($step->stepId === $stepId) {
                return $step;
            }
        }

        return null;
    }

    private function continueNormally(Workflow $workflow, Step $step, WorkflowContext $context, string $executionId): void
    {
        $newContext = $context->withStepStatus($step->stepId, StepStatus::Succeeded);
        $this->engine->evaluate($workflow, $newContext);
    }

    private function terminate(WorkflowContext $context, string $executionId, ExecutionStatus $status, string $eventType): void
    {
        $this->executionRegistry->complete($executionId, $status);
        $this->eventLedger->append($executionId, $eventType, ['workflowId' => $context->getWorkflowId()]);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/StepOutcomeHandlerTest.php`
Expected: PASS (8 tests)

- [ ] **Step 6: Commit**

```bash
git add src/Execution/Exceptions/GotoTargetNotFoundException.php src/Execution/StepOutcomeHandler.php tests/Execution/StepOutcomeHandlerTest.php
git commit -m "feat: add StepOutcomeHandler with retry-action handling"
```

---

## Task 7: `StepOutcomeHandler` — goto handling (same-workflow and cross-workflow)

**Files:**
- Modify: `src/Execution/StepOutcomeHandler.php`
- Modify: `tests/Execution/StepOutcomeHandlerTest.php`

**Interfaces:**
- Produces: `applyFirstMatch()`'s dispatch now handles `SuccessGotoAction`/`FailureGotoAction` — same-workflow stepId jump (bypassing `DependencyAnalyzer`, resetting an already-`Succeeded` target back to `Pending` so loop-backs re-run), cross-workflow jump via `$action->workflowId` (looked up in `$document->workflows`), and workflow-only jump (no `stepId`) that hands off to `Engine::evaluate()` for normal dependency-driven entry. Unknown `stepId`/`workflowId` throws `GotoTargetNotFoundException`. `SuccessEndAction`/`FailureEndAction` still throw the Task 6 `LogicException` fallback — Task 8 replaces that.

- [ ] **Step 1: Write the failing tests**

Add these tests to `tests/Execution/StepOutcomeHandlerTest.php` (append after the existing tests, before the final closing of the file — there's no class wrapper to worry about, just add more top-level `it(...)` calls):

```php
it('goto jumps to a same-workflow step directly, bypassing DependencyAnalyzer ordering', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler();

    $goto = new FailureGotoAction('goto-c', 'C', null, []);
    $stepA = stepOutcomeStep('A', onFailure: [$goto]);
    $stepB = stepOutcomeStep('B');
    $stepC = stepOutcomeStep('C', dependsOn: ['B']); // C would not normally be runnable yet
    $workflow = stepOutcomeWorkflow('wf_1', [$stepA, $stepB, $stepC]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $stepA, $context, 'exec_1', false);

    expect($queue->dispatched)->toHaveCount(1);
    $job = $queue->dispatched[0]['job'];
    expect($job->step->stepId)->toBe('C');
    expect($job->context->getStepStatus('C'))->toBe(StepStatus::Pending);
});

it('goto loop-back resets an already-succeeded target step to Pending so it re-runs', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler();

    $goto = new FailureGotoAction('goto-a', 'A', null, []);
    $stepA = stepOutcomeStep('A');
    $stepB = stepOutcomeStep('B', onFailure: [$goto], dependsOn: ['A']);
    $workflow = stepOutcomeWorkflow('wf_1', [$stepA, $stepB]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))
        ->withWorkflowId('wf_1')
        ->withExecutionId('exec_1')
        ->withStepResult('A', ['statusCode' => 200])
        ->withStepStatus('A', StepStatus::Succeeded);

    $handler->handle($document, $workflow, $stepB, $context, 'exec_1', false);

    $job = $queue->dispatched[0]['job'];
    expect($job->step->stepId)->toBe('A');
    expect($job->context->getStepStatus('A'))->toBe(StepStatus::Pending);
});

it('goto to a workflowId-only target hands off to Engine::evaluate for the target workflow entry steps', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler();

    $goto = new FailureGotoAction('goto-wf2', null, 'wf_2', []);
    $stepA = stepOutcomeStep('A', onFailure: [$goto]);
    $workflow1 = stepOutcomeWorkflow('wf_1', [$stepA]);
    $entryStep = stepOutcomeStep('entry');
    $workflow2 = stepOutcomeWorkflow('wf_2', [$entryStep]);
    $document = stepOutcomeDocument([$workflow1, $workflow2]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow1, $stepA, $context, 'exec_1', false);

    expect($queue->dispatched)->toHaveCount(1);
    $job = $queue->dispatched[0]['job'];
    expect($job->step->stepId)->toBe('entry');
    expect($job->context->getWorkflowId())->toBe('wf_2');
});

it('goto with both workflowId and stepId jumps directly to that step in the target workflow', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler();

    $goto = new FailureGotoAction('goto-wf2-mid', 'mid', 'wf_2', []);
    $stepA = stepOutcomeStep('A', onFailure: [$goto]);
    $workflow1 = stepOutcomeWorkflow('wf_1', [$stepA]);
    $entryStep = stepOutcomeStep('entry');
    $midStep = stepOutcomeStep('mid', dependsOn: ['entry']);
    $workflow2 = stepOutcomeWorkflow('wf_2', [$entryStep, $midStep]);
    $document = stepOutcomeDocument([$workflow1, $workflow2]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow1, $stepA, $context, 'exec_1', false);

    $job = $queue->dispatched[0]['job'];
    expect($job->step->stepId)->toBe('mid');
    expect($job->context->getWorkflowId())->toBe('wf_2');
});

it('goto to an unknown workflowId throws GotoTargetNotFoundException', function (): void {
    [$handler] = makeStepOutcomeHandler();

    $goto = new FailureGotoAction('goto-missing', null, 'does-not-exist', []);
    $step = stepOutcomeStep('A', onFailure: [$goto]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    expect(fn () => $handler->handle($document, $workflow, $step, $context, 'exec_1', false))
        ->toThrow(\Alama\LaravelArazzo\Execution\Exceptions\GotoTargetNotFoundException::class);
});

it('goto to an unknown stepId in the current workflow throws GotoTargetNotFoundException', function (): void {
    [$handler] = makeStepOutcomeHandler();

    $goto = new FailureGotoAction('goto-missing-step', 'nope', null, []);
    $step = stepOutcomeStep('A', onFailure: [$goto]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    expect(fn () => $handler->handle($document, $workflow, $step, $context, 'exec_1', false))
        ->toThrow(\Alama\LaravelArazzo\Execution\Exceptions\GotoTargetNotFoundException::class);
});
```

Also add `use Alama\LaravelArazzo\Dto\Action\FailureGotoAction;` to the test file's imports if not already present (it was added in Task 6's version already).

- [ ] **Step 2: Run tests to verify the new ones fail**

Run: `vendor/bin/pest tests/Execution/StepOutcomeHandlerTest.php`
Expected: the Task 6 tests still PASS; the 6 new goto tests FAIL — `applyFirstMatch()` throws `LogicException('Unhandled action type: ...')` for `FailureGotoAction`.

- [ ] **Step 3: Add goto handling**

In `src/Execution/StepOutcomeHandler.php`, add these imports alongside the existing ones:

```php
use Alama\LaravelArazzo\Dto\Action\FailureGotoAction;
use Alama\LaravelArazzo\Dto\Action\SuccessGotoAction;
```

Replace the `applyFirstMatch` method's dispatch section — the block that currently reads:

```php
        if ($matched instanceof RetryAction) {
            $this->handleRetry($matched, $actions, $document, $workflow, $step, $context, $executionId, $criteriaMet);

            return;
        }

        throw new LogicException('Unhandled action type: ' . $matched::class);
```

with:

```php
        if ($matched instanceof RetryAction) {
            $this->handleRetry($matched, $actions, $document, $workflow, $step, $context, $executionId, $criteriaMet);

            return;
        }

        if ($matched instanceof SuccessGotoAction || $matched instanceof FailureGotoAction) {
            $this->handleGoto($matched, $document, $context, $executionId);

            return;
        }

        throw new LogicException('Unhandled action type: ' . $matched::class);
```

Add this new private method to the class (placed after `handleRetry`, before `resolveTarget` is fine):

```php
    private function handleGoto(SuccessGotoAction|FailureGotoAction $action, ArazzoDocument $document, WorkflowContext $context, string $executionId): void
    {
        $targetWorkflowId = $action->workflowId ?? $context->getWorkflowId();
        $targetWorkflow = $this->findWorkflow($document, $targetWorkflowId);
        if ($targetWorkflow === null) {
            throw new GotoTargetNotFoundException("Goto action '{$action->name}' references unknown workflowId '{$targetWorkflowId}'.");
        }

        $newContext = $targetWorkflow->workflowId !== $context->getWorkflowId()
            ? $context->withWorkflowId($targetWorkflow->workflowId)
            : $context;

        if ($action->stepId === null) {
            // No specific step named -- transfer to the target workflow's start, letting
            // normal dependency-driven choreography pick its entry steps.
            $this->engine->evaluate($targetWorkflow, $newContext);

            return;
        }

        $targetStep = $this->findStep($targetWorkflow, $action->stepId);
        if ($targetStep === null) {
            throw new GotoTargetNotFoundException("Goto action '{$action->name}' references unknown stepId '{$action->stepId}' in workflow '{$targetWorkflow->workflowId}'.");
        }

        $newContext = $newContext->withStepStatus($targetStep->stepId, StepStatus::Pending);
        $this->queueDriver->dispatch(new ExecuteStepJob($targetStep, $newContext));
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/StepOutcomeHandlerTest.php`
Expected: PASS (14 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Execution/StepOutcomeHandler.php tests/Execution/StepOutcomeHandlerTest.php
git commit -m "feat: add goto-action handling to StepOutcomeHandler"
```

---

## Task 8: `StepOutcomeHandler` — end actions + auto-complete-on-drain

**Files:**
- Modify: `src/Execution/StepOutcomeHandler.php`
- Modify: `tests/Execution/StepOutcomeHandlerTest.php`

**Interfaces:**
- Consumes: `PendingCorrelationRegistryInterface::existsForExecution()` (Task 5).
- Produces: constructor gains `DependencyAnalyzer $dependencyAnalyzer` and `PendingCorrelationRegistryInterface $pendingCorrelations` params (inserted after `Engine`, before `ExecutionRegistryInterface` — see full signature below). `SuccessEndAction`/`FailureEndAction` now terminate the execution instead of hitting the `LogicException` fallback. The implicit-continue path (`criteriaMet` true, no action matched) now auto-completes the execution as `Succeeded` once `DependencyAnalyzer::getRunnableSteps()` is empty **and** no `PendingCorrelation` is outstanding for this execution — this is the "ran out of work" completion path for workflows with no explicit `end` action.

- [ ] **Step 1: Write the failing tests**

In `tests/Execution/StepOutcomeHandlerTest.php`, add this fake alongside the other mock classes near the top of the file (after `StepOutcomeMockEventLedger`, before `StepOutcomeMockExpressionResolver`):

```php
class StepOutcomeMockPendingCorrelationRegistry implements \Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface
{
    /** @var array<string, bool> */
    public array $outstanding = [];

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void
    {
        $this->outstanding[$executionId] = true;
    }

    public function findByCorrelationId(string $correlationId): ?\Alama\LaravelArazzo\Execution\PendingCorrelation
    {
        return null;
    }

    public function consume(string $correlationId): void
    {
    }

    public function existsForExecution(string $executionId): bool
    {
        return $this->outstanding[$executionId] ?? false;
    }
}
```

Replace the `makeStepOutcomeHandler()` helper function with this version (adds the two new constructor args and returns the pending-correlation fake too):

```php
/**
 * @return array{0: StepOutcomeHandler, 1: SyncQueueDriver, 2: StepOutcomeMockExecutionRegistry, 3: StepOutcomeMockEventLedger, 4: StepOutcomeMockPendingCorrelationRegistry}
 */
function makeStepOutcomeHandler(int $maxRetryAttempts = 10, bool $pendingCorrelationOutstanding = false): array
{
    $queue = new SyncQueueDriver();
    $executionRegistry = new StepOutcomeMockExecutionRegistry();
    $eventLedger = new StepOutcomeMockEventLedger();
    $pendingCorrelations = new StepOutcomeMockPendingCorrelationRegistry();
    if ($pendingCorrelationOutstanding) {
        $pendingCorrelations->outstanding['exec_1'] = true;
    }
    $dependencyAnalyzer = new DependencyAnalyzer();
    $engine = new Engine($dependencyAnalyzer, $queue, new class implements \Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface {
        public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
        {
        }

        public function load(string $executionId): ?array
        {
            return null;
        }
    });
    $resolver = new StepOutcomeMockExpressionResolver();

    $handler = new StepOutcomeHandler($queue, $engine, $dependencyAnalyzer, $executionRegistry, $eventLedger, $pendingCorrelations, $resolver, $maxRetryAttempts);

    return [$handler, $queue, $executionRegistry, $eventLedger, $pendingCorrelations];
}
```

This changes the return shape every existing test destructures — since every existing test only destructures the leading elements it needs (e.g. `[$handler, $queue] = ...`), none of them break from the trailing 5th element being added.

Now add these new tests at the end of the file:

```php
it('SuccessEndAction terminates the execution as succeeded', function (): void {
    [$handler, $queue, $executionRegistry, $eventLedger] = makeStepOutcomeHandler();

    $end = new \Alama\LaravelArazzo\Dto\Action\SuccessEndAction('end-ok', []);
    $step = stepOutcomeStep('A', onSuccess: [$end]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', true);

    expect($queue->dispatched)->toBeEmpty();
    expect($executionRegistry->completed[0]['status'])->toBe(ExecutionStatus::Succeeded);
    expect($eventLedger->appended[0]['eventType'])->toBe('execution.succeeded');
});

it('FailureEndAction terminates the execution as failed', function (): void {
    [$handler, , $executionRegistry, $eventLedger] = makeStepOutcomeHandler();

    $end = new \Alama\LaravelArazzo\Dto\Action\FailureEndAction('end-fail', []);
    $step = stepOutcomeStep('A', onFailure: [$end]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', false);

    expect($executionRegistry->completed[0]['status'])->toBe(ExecutionStatus::Failed);
    expect($eventLedger->appended[0]['eventType'])->toBe('execution.failed');
});

it('auto-completes the execution as succeeded once no steps are runnable and nothing is suspended', function (): void {
    [$handler, , $executionRegistry] = makeStepOutcomeHandler();

    $step = stepOutcomeStep('A');
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', true);

    expect($executionRegistry->completed)->toHaveCount(1);
    expect($executionRegistry->completed[0]['status'])->toBe(ExecutionStatus::Succeeded);
});

it('does not auto-complete while a PendingCorrelation is still outstanding for the execution', function (): void {
    [$handler, , $executionRegistry] = makeStepOutcomeHandler(pendingCorrelationOutstanding: true);

    $step = stepOutcomeStep('A');
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', true);

    expect($executionRegistry->completed)->toBeEmpty();
});

it('does not auto-complete while downstream steps are still runnable', function (): void {
    [$handler, $queue, $executionRegistry] = makeStepOutcomeHandler();

    $stepA = stepOutcomeStep('A');
    $stepB = stepOutcomeStep('B', dependsOn: ['A']);
    $workflow = stepOutcomeWorkflow('wf_1', [$stepA, $stepB]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $stepA, $context, 'exec_1', true);

    expect($queue->dispatched)->toHaveCount(1);
    expect($executionRegistry->completed)->toBeEmpty();
});
```

- [ ] **Step 2: Run tests to verify the new ones fail**

Run: `vendor/bin/pest tests/Execution/StepOutcomeHandlerTest.php`
Expected: constructor-signature tests FAIL immediately (`StepOutcomeHandler`'s constructor doesn't accept the new args yet) — this is expected until Step 3 lands; the file won't even parse-execute correctly until then.

- [ ] **Step 3: Add end-action handling and auto-complete-on-drain**

In `src/Execution/StepOutcomeHandler.php`, add these imports:

```php
use Alama\LaravelArazzo\Dto\Action\FailureEndAction;
use Alama\LaravelArazzo\Dto\Action\SuccessEndAction;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
```

Replace the constructor:

```php
    public function __construct(
        private QueueDriverInterface $queueDriver,
        private Engine $engine,
        private DependencyAnalyzer $dependencyAnalyzer,
        private ExecutionRegistryInterface $executionRegistry,
        private EventLedgerInterface $eventLedger,
        private PendingCorrelationRegistryInterface $pendingCorrelations,
        private ExpressionResolverInterface $expressionResolver,
        private int $maxRetryAttempts = 10,
    ) {
    }
```

Replace the `applyFirstMatch` method's final fallback — the block that currently reads:

```php
        if ($matched instanceof SuccessGotoAction || $matched instanceof FailureGotoAction) {
            $this->handleGoto($matched, $document, $context, $executionId);

            return;
        }

        throw new LogicException('Unhandled action type: ' . $matched::class);
```

with:

```php
        if ($matched instanceof SuccessGotoAction || $matched instanceof FailureGotoAction) {
            $this->handleGoto($matched, $document, $context, $executionId);

            return;
        }

        $status = $matched instanceof SuccessEndAction ? ExecutionStatus::Succeeded : ExecutionStatus::Failed;
        $this->terminate(
            $context,
            $executionId,
            $status,
            $status === ExecutionStatus::Succeeded ? 'execution.succeeded' : 'execution.failed',
        );
```

Replace `continueNormally`:

```php
    private function continueNormally(Workflow $workflow, Step $step, WorkflowContext $context, string $executionId): void
    {
        $newContext = $context->withStepStatus($step->stepId, StepStatus::Succeeded);
        $this->engine->evaluate($workflow, $newContext);

        $runnable = $this->dependencyAnalyzer->getRunnableSteps($workflow->steps, $newContext);
        if ($runnable === [] && !$this->pendingCorrelations->existsForExecution($executionId)) {
            $this->terminate($newContext, $executionId, ExecutionStatus::Succeeded, 'execution.succeeded');
        }
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/StepOutcomeHandlerTest.php`
Expected: PASS (19 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Execution/StepOutcomeHandler.php tests/Execution/StepOutcomeHandlerTest.php
git commit -m "feat: add end-action handling and auto-complete-on-drain to StepOutcomeHandler"
```

---

## Task 9: `StepExecutionOutcome` + `StepProtocolExecutorInterface` + `HttpStepExecutor`

**Files:**
- Create: `src/Execution/StepExecutionOutcome.php`
- Create: `src/Execution/Contracts/StepProtocolExecutorInterface.php`
- Create: `src/Execution/HttpStepExecutor.php`
- Test: `tests/Execution/HttpStepExecutorTest.php`

**Interfaces:**
- Produces: `StepExecutionOutcome` (readonly value object with `suspended`, `statusCode`, `outputs`, `responseBody`, and named constructors `resolved()`/`suspended()`); `StepProtocolExecutorInterface` (`supports()`/`execute()`); `HttpStepExecutor` — extracted from the compileRequest/sendRequest/extractOutputs flow currently inline in `StepExecutionWorker`. Consumed by Task 12 (`AsyncApiStepExecutor`, same interface), Task 13 (`StepExecutionWorker`).
- **Bug fix folded in:** doc 02's target `StepExecutionWorker` calls `extractOutputs()` with the context *before* the response was ever stored on it — any output expression that reads `$steps.<id>.response.body#/...` via a JSON pointer would silently read stale/empty data. `HttpStepExecutor::execute()` stores the response on the context (`withStepResponse`) before calling `extractOutputs()`, fixing the ordering.

- [ ] **Step 1: Write the failing tests**

Create `tests/Execution/HttpStepExecutorTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\HttpStepExecutor;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HttpStepExecutorMockResolver implements \Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface
{
    public ?WorkflowContext $lastContextSeenByExtractOutputs = null;

    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
    {
        return new Request('GET', 'http://localhost/thing');
    }

    /** @return array<string, mixed> */
    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        $this->lastContextSeenByExtractOutputs = $context;

        return ['echoedBody' => $context->getSteps()[$step->stepId]['response']['body'] ?? null];
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }
}

class HttpStepExecutorMockClient implements HttpClientInterface
{
    public function __construct(private ResponseInterface $response)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}

function httpStepExecutorDocument(): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), []);
}

it('supports a step with no action set', function (): void {
    $executor = new HttpStepExecutor(new HttpStepExecutorMockClient(new Response(200)), new HttpStepExecutorMockResolver());
    $step = new Step('s1', null, null, null, null, [], null, [], [], [], []);

    expect($executor->supports($step, httpStepExecutorDocument()))->toBeTrue();
});

it('does not support a step with an action set', function (): void {
    $executor = new HttpStepExecutor(new HttpStepExecutorMockClient(new Response(200)), new HttpStepExecutorMockResolver());
    $step = new Step('s1', null, null, null, null, [], null, [], [], [], [], [], 'send');

    expect($executor->supports($step, httpStepExecutorDocument()))->toBeFalse();
});

it('executes the request and returns a resolved outcome with statusCode/outputs/body', function (): void {
    $response = new Response(201, [], json_encode(['id' => 42]));
    $client = new HttpStepExecutorMockClient($response);
    $resolver = new HttpStepExecutorMockResolver();
    $executor = new HttpStepExecutor($client, $resolver);

    $step = new Step('s1', null, null, null, null, [], null, [], [], [], []);
    $context = new WorkflowContext('def_1', [], [], [], 'wf_1', 'exec_1');

    $outcome = $executor->execute($step, $context, httpStepExecutorDocument(), 'exec_1');

    expect($outcome->suspended)->toBeFalse();
    expect($outcome->statusCode)->toBe(201);
    expect($outcome->responseBody)->toBe(['id' => 42]);
    expect($outcome->outputs)->toBe(['echoedBody' => ['id' => 42]]);
});

it('stores the response on the context before calling extractOutputs, fixing the stale-context ordering bug', function (): void {
    $response = new Response(200, [], json_encode(['x' => 1]));
    $client = new HttpStepExecutorMockClient($response);
    $resolver = new HttpStepExecutorMockResolver();
    $executor = new HttpStepExecutor($client, $resolver);

    $step = new Step('s1', null, null, null, null, [], null, [], [], [], []);
    $context = new WorkflowContext('def_1');

    $executor->execute($step, $context, httpStepExecutorDocument(), 'exec_1');

    expect($resolver->lastContextSeenByExtractOutputs->getSteps()['s1']['response']['body'])->toBe(['x' => 1]);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/HttpStepExecutorTest.php`
Expected: FAIL — `HttpStepExecutor` doesn't exist; `Step` constructor doesn't accept a positional 12th `action` arg yet (that lands in Task 10 — if you're executing tasks strictly in order, this test file won't even parse-construct correctly until Task 10 lands; note it and come back to verify after Task 10, same cross-task-dependency pattern doc 02's plan used for its migrations).

- [ ] **Step 3: Create `StepExecutionOutcome`**

Create `src/Execution/StepExecutionOutcome.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

final readonly class StepExecutionOutcome
{
    /**
     * @param array<string, mixed> $outputs
     * @param array<string, mixed> $responseBody
     */
    private function __construct(
        public bool $suspended,
        public ?int $statusCode = null,
        public array $outputs = [],
        public array $responseBody = [],
    ) {
    }

    /**
     * @param array<string, mixed> $outputs
     * @param array<string, mixed> $responseBody
     */
    public static function resolved(int $statusCode, array $outputs, array $responseBody): self
    {
        return new self(false, $statusCode, $outputs, $responseBody);
    }

    public static function suspended(): self
    {
        return new self(true);
    }
}
```

- [ ] **Step 4: Create `StepProtocolExecutorInterface`**

Create `src/Execution/Contracts/StepProtocolExecutorInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Contracts;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\StepExecutionOutcome;
use Alama\LaravelArazzo\Execution\WorkflowContext;

interface StepProtocolExecutorInterface
{
    public function supports(Step $step, ArazzoDocument $document): bool;

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome;
}
```

- [ ] **Step 5: Implement `HttpStepExecutor`**

Create `src/Execution/HttpStepExecutor.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\StepProtocolExecutorInterface;

final class HttpStepExecutor implements StepProtocolExecutorInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ExpressionResolverInterface $expressionResolver,
    ) {
    }

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return $step->action === null;
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        $request = $this->expressionResolver->compileRequest($step, $context, $document);
        $response = $this->httpClient->sendRequest($request);

        $decodedBody = json_decode((string) $response->getBody(), true);
        $body = is_array($decodedBody) ? $decodedBody : [];

        $contextWithResponse = $context->withStepResponse($step->stepId, [
            'statusCode' => $response->getStatusCode(),
            'body' => $body,
        ]);

        $outputs = $this->expressionResolver->extractOutputs($step, $contextWithResponse, $document);

        return StepExecutionOutcome::resolved($response->getStatusCode(), $outputs, $body);
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/HttpStepExecutorTest.php`
Expected: PASS (4 tests) once Task 10's `Step` DTO change has landed.

- [ ] **Step 7: Commit**

```bash
git add src/Execution/StepExecutionOutcome.php src/Execution/Contracts/StepProtocolExecutorInterface.php src/Execution/HttpStepExecutor.php tests/Execution/HttpStepExecutorTest.php
git commit -m "feat: extract HttpStepExecutor behind StepProtocolExecutorInterface"
```

---

## Task 10: `Step` DTO + `Parser` — AsyncAPI `action`/`channelPath`/`correlationId` fields

**Files:**
- Modify: `src/Dto/Step.php`
- Modify: `src/Parser/Parser.php`
- Test: `tests/Parser/FullParserTest.php` (existing file — full-document parse tests live here; there's no `ParserTest.php`, and step-level fields aren't leaf-probed in `LeafParserTest.php`'s `LeafProbe`, so a full-document round trip is the right level for these two new fields)

**Interfaces:**
- Produces: `Step` gains 3 new trailing optional constructor params: `?string $action = null`, `?string $channelPath = null`, `?Expression $correlationId = null` (after the existing `dependsOn` param — purely additive, every existing named-arg call site across `src/`/`tests/` keeps compiling unchanged, and the handful of positional call sites in this codebase all stop at 12 positional args, which remains valid). `Parser::parseStep()` reads `action`/`channelPath`/`correlationId` from the YAML/JSON when present. Consumed by Task 9's `HttpStepExecutor::supports()` (already written against `$step->action === null`), Task 12 (`AsyncApiStepExecutor`).
- These are Arazzo 1.1 constructs, not part of today's 1.0.0 parsing — no validation rule enforces `action`/`channelPath`/`correlationId` co-occurrence in this task; that's deliberately left to validation work, out of scope here (this plan only needs the fields to reach the DTO so the execution layer can act on them).

- [ ] **Step 1: Write the failing test**

Add `use Alama\LaravelArazzo\Dto\RawDocument;` and `use Alama\LaravelArazzo\Dto\Enum\Format;` to `tests/Parser/FullParserTest.php`'s imports (alongside the existing `use Alama\LaravelArazzo\Parser\Parser;` etc. — this file currently parses from fixture files via `Loader`, these two new tests parse an inline array instead, which is an existing, established pattern elsewhere in this codebase, e.g. `tests/Unit/Laravel/DatabaseDefinitionRegistryTest.php` from doc 02's plan). Add these tests to the end of `tests/Parser/FullParserTest.php`:

```php
it('parses AsyncAPI action/channelPath/correlationId fields on a step', function (): void {
    $raw = [
        'arazzo' => '1.0.0',
        'info' => ['title' => 'T', 'version' => '1.0'],
        'sourceDescriptions' => [],
        'workflows' => [
            [
                'workflowId' => 'wf_1',
                'steps' => [
                    [
                        'stepId' => 'wait-for-ride',
                        'action' => 'receive',
                        'channelPath' => 'channels/rides/created',
                        'correlationId' => '{$response.body#/rideId}',
                    ],
                ],
            ],
        ],
    ];

    $document = (new Parser())->parse(new RawDocument($raw, 'memory://test', Format::Json));
    $step = $document->workflows[0]->steps[0];

    expect($step->action)->toBe('receive');
    expect($step->channelPath)->toBe('channels/rides/created');
    expect($step->correlationId->raw)->toBe('{$response.body#/rideId}');
});

it('leaves action/channelPath/correlationId null when absent', function (): void {
    $raw = [
        'arazzo' => '1.0.0',
        'info' => ['title' => 'T', 'version' => '1.0'],
        'sourceDescriptions' => [],
        'workflows' => [
            ['workflowId' => 'wf_1', 'steps' => [['stepId' => 's1', 'operationId' => 'op']]],
        ],
    ];

    $document = (new Parser())->parse(new RawDocument($raw, 'memory://test', Format::Json));
    $step = $document->workflows[0]->steps[0];

    expect($step->action)->toBeNull();
    expect($step->channelPath)->toBeNull();
    expect($step->correlationId)->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Parser/FullParserTest.php --filter="action.*channelPath.*correlationId|action.*absent"`
Expected: FAIL — `Step` has no `action`/`channelPath`/`correlationId` properties.

- [ ] **Step 3: Extend the `Step` DTO**

In `src/Dto/Step.php`, add `use Alama\LaravelArazzo\Dto\Expression;` to the imports (alongside `FailureAction`/`SuccessAction`), and replace the constructor:

```php
    public function __construct(
        public string $stepId,
        public ?string $description,
        public ?string $operationId,
        public ?string $operationPath,
        public ?string $workflowId,
        public array $parameters,
        public ?RequestBody $requestBody,
        public array $successCriteria,
        public array $onSuccess,
        public array $onFailure,
        public array $outputs,
        public array $dependsOn = [],
        public ?string $action = null,
        public ?string $channelPath = null,
        public ?Expression $correlationId = null,
    ) {
    }
```

- [ ] **Step 4: Parse the new fields**

In `src/Parser/Parser.php`, inside `parseStep()`, add these lines right before the final `return new Step(...)` statement (after the existing `$outputs = ...` block):

```php
        $action = $this->optionalString($obj, 'action', $ctx);
        $channelPath = $this->optionalString($obj, 'channelPath', $ctx);
        $correlationIdRaw = $this->optionalString($obj, 'correlationId', $ctx);
        $correlationId = $correlationIdRaw !== null ? new Expression($correlationIdRaw) : null;
```

Then replace the `return new Step(...)` call:

```php
        return new Step(
            stepId: $this->requireString($obj, 'stepId', $ctx),
            description: $this->optionalString($obj, 'description', $ctx),
            operationId: $this->optionalString($obj, 'operationId', $ctx),
            operationPath: $this->optionalString($obj, 'operationPath', $ctx),
            workflowId: $this->optionalString($obj, 'workflowId', $ctx),
            parameters: $parameters,
            requestBody: $requestBody,
            successCriteria: $criteria,
            onSuccess: $onSuccess,
            onFailure: $onFailure,
            outputs: $outputs,
            action: $action,
            channelPath: $channelPath,
            correlationId: $correlationId,
        );
    }
```

(`Expression` is already imported in `Parser.php` — it's used elsewhere in the same file, e.g. in `parseOutputsMap`.)

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Parser/FullParserTest.php`
Expected: PASS, including the 2 new tests and every pre-existing test in this file (no positional `new Step(...)` call site in `src/` or `tests/` exceeds 12 args, so nothing else in the suite breaks — you can double check with `grep -rn "new Step(" src tests` and eyeballing arg counts on any non-named-arg calls).

- [ ] **Step 6: Run the full suite once to catch any other Step(...) call site**

Run: `vendor/bin/pest`
Expected: PASS. If anything fails, it means some call site constructs `Step` positionally with more than 12 args in a way that now collides with the new params — fix that call site to use named args (matching the dominant style already used everywhere else in this codebase) rather than reordering the new params.

- [ ] **Step 7: Commit**

```bash
git add src/Dto/Step.php src/Parser/Parser.php tests/Parser/FullParserTest.php
git commit -m "feat: parse AsyncAPI action/channelPath/correlationId fields on Step"
```

---

## Task 11: `SourceType::Asyncapi` + `AsyncApiResolvedSource` + `AsyncApiSourceParser`

**Files:**
- Modify: `src/Dto/Enum/SourceType.php`
- Create: `src/Resolution/AsyncApiResolvedSource.php`
- Create: `src/Resolution/Parsers/AsyncApiSourceParser.php`
- Test: `tests/Resolution/AsyncApiResolvedSourceTest.php`
- Test: `tests/Resolution/Parsers/AsyncApiSourceParserTest.php`

**Interfaces:**
- Produces: `SourceType::Asyncapi = 'asyncapi'`; `AsyncApiResolvedSource implements ResolvedSource` (wraps a plain decoded array, JSON-pointer `extract()` — no cebe/php-openapi-style object model, since this codebase has no AsyncAPI schema library dependency and `AsyncApiStepExecutor` (Task 12) doesn't need deep operation resolution, only the step's own `channelPath`/`correlationId`); `AsyncApiSourceParser implements SourceParser`. Consumed by Task 16 (service provider's source-parser map).
- Deep AsyncAPI schema validation (channel/operation binding resolution) is out of scope — mirrors how this codebase's `SourceTypeMatchesRule`/`StepOperationIdSourceScopedRule` today only strictly validate OpenAPI-sourced `operationId`s.

- [ ] **Step 1: Write the failing tests**

Create `tests/Resolution/AsyncApiResolvedSourceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Resolution;

use Alama\LaravelArazzo\Resolution\AsyncApiResolvedSource;
use Alama\LaravelArazzo\Resolution\Exceptions\UnresolvableReferenceException;

it('extracts the whole document for an empty pointer', function (): void {
    $source = new AsyncApiResolvedSource(['asyncapi' => '2.6.0', 'channels' => ['x' => []]]);

    expect($source->extract(''))->toBe(['asyncapi' => '2.6.0', 'channels' => ['x' => []]]);
});

it('extracts a nested value by json pointer', function (): void {
    $source = new AsyncApiResolvedSource(['channels' => ['rides/created' => ['subscribe' => ['operationId' => 'onRideCreated']]]]);

    expect($source->extract('/channels/rides~1created/subscribe/operationId'))->toBe('onRideCreated');
});

it('throws for an unresolvable pointer', function (): void {
    $source = new AsyncApiResolvedSource(['channels' => []]);

    expect(fn () => $source->extract('/channels/missing'))->toThrow(UnresolvableReferenceException::class);
});
```

Create `tests/Resolution/Parsers/AsyncApiSourceParserTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Resolution\Parsers;

use Alama\LaravelArazzo\Resolution\AsyncApiResolvedSource;
use Alama\LaravelArazzo\Resolution\Exceptions\SourceParseException;
use Alama\LaravelArazzo\Resolution\Parsers\AsyncApiSourceParser;

it('parses a JSON AsyncAPI document', function (): void {
    $parser = new AsyncApiSourceParser();

    $resolved = $parser->parse(json_encode(['asyncapi' => '2.6.0', 'channels' => []]));

    expect($resolved)->toBeInstanceOf(AsyncApiResolvedSource::class);
    expect($resolved->extract('/asyncapi'))->toBe('2.6.0');
});

it('parses a YAML AsyncAPI document', function (): void {
    $parser = new AsyncApiSourceParser();

    $resolved = $parser->parse("asyncapi: 2.6.0\nchannels: {}\n");

    expect($resolved->extract('/asyncapi'))->toBe('2.6.0');
});

it('throws SourceParseException for invalid content', function (): void {
    $parser = new AsyncApiSourceParser();

    expect(fn () => $parser->parse("not: [valid\n"))->toThrow(SourceParseException::class);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Resolution/AsyncApiResolvedSourceTest.php tests/Resolution/Parsers/AsyncApiSourceParserTest.php`
Expected: FAIL — neither class exists.

- [ ] **Step 3: Add the enum case**

In `src/Dto/Enum/SourceType.php`, replace the full contents:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto\Enum;

enum SourceType: string
{
    case Openapi = 'openapi';
    case Arazzo = 'arazzo';
    case Asyncapi = 'asyncapi';
}
```

- [ ] **Step 4: Implement `AsyncApiResolvedSource`**

Create `src/Resolution/AsyncApiResolvedSource.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Resolution;

use Alama\LaravelArazzo\Resolution\Exceptions\UnresolvableReferenceException;

final readonly class AsyncApiResolvedSource implements ResolvedSource
{
    /**
     * @param array<string, mixed> $document
     */
    public function __construct(private array $document)
    {
    }

    /**
     * @throws UnresolvableReferenceException
     */
    public function extract(string $jsonPointer): mixed
    {
        $current = $this->document;

        $trimmed = trim($jsonPointer, '/');
        if ($trimmed === '') {
            return $current;
        }

        $parts = explode('/', $trimmed);

        foreach ($parts as $part) {
            $part = str_replace(['~1', '~0'], ['/', '~'], $part);

            if (is_array($current) && array_key_exists($part, $current)) {
                $current = $current[$part];
            } else {
                throw new UnresolvableReferenceException("Path not found: {$jsonPointer}");
            }
        }

        return $current;
    }
}
```

- [ ] **Step 5: Implement `AsyncApiSourceParser`**

Create `src/Resolution/Parsers/AsyncApiSourceParser.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Resolution\Parsers;

use Alama\LaravelArazzo\Loader\NativeJsonDecoder;
use Alama\LaravelArazzo\Loader\SymfonyYamlDecoder;
use Alama\LaravelArazzo\Resolution\AsyncApiResolvedSource;
use Alama\LaravelArazzo\Resolution\Exceptions\SourceParseException;
use Alama\LaravelArazzo\Resolution\ResolvedSource;
use Alama\LaravelArazzo\Resolution\SourceParser;
use Throwable;

final class AsyncApiSourceParser implements SourceParser
{
    public function parse(string $content): ResolvedSource
    {
        try {
            $isYaml = !str_starts_with(trim($content), '{');
            $decoded = $isYaml
                ? (new SymfonyYamlDecoder())->decode($content)
                : (new NativeJsonDecoder())->decode($content);

            if (!is_array($decoded)) {
                throw new SourceParseException('AsyncAPI document root must be an object');
            }

            /** @var array<string, mixed> $decoded */
            return new AsyncApiResolvedSource($decoded);
        } catch (SourceParseException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new SourceParseException('Failed to parse AsyncAPI document: ' . $e->getMessage(), 0, $e);
        }
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Resolution/AsyncApiResolvedSourceTest.php tests/Resolution/Parsers/AsyncApiSourceParserTest.php`
Expected: PASS (6 tests)

- [ ] **Step 7: Commit**

```bash
git add src/Dto/Enum/SourceType.php src/Resolution/AsyncApiResolvedSource.php src/Resolution/Parsers/AsyncApiSourceParser.php tests/Resolution/AsyncApiResolvedSourceTest.php tests/Resolution/Parsers/AsyncApiSourceParserTest.php
git commit -m "feat: add SourceType::Asyncapi, AsyncApiResolvedSource, AsyncApiSourceParser"
```

---

## Task 12: `AsyncApiStepExecutor`

**Files:**
- Create: `src/Execution/AsyncApiStepExecutor.php`
- Test: `tests/Execution/AsyncApiStepExecutorTest.php`

**Interfaces:**
- Consumes: `StepProtocolExecutorInterface` (Task 9), `PendingCorrelationRegistryInterface` (Task 5), `Step::$action`/`$channelPath`/`$correlationId` (Task 10).
- Produces: `AsyncApiStepExecutor implements StepProtocolExecutorInterface` — `action: 'send'` steps publish via the existing HTTP client stack and resolve immediately; `action: 'receive'` steps write a `PendingCorrelation` and return `StepExecutionOutcome::suspended()`. Consumed by Task 13 (`StepExecutionWorker`), Task 16 (service provider wiring).

- [ ] **Step 1: Write the failing tests**

Create `tests/Execution/AsyncApiStepExecutorTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\AsyncApiStepExecutor;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\PendingCorrelation;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class AsyncApiExecutorMockClient implements HttpClientInterface
{
    public ?RequestInterface $lastRequest = null;

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        return new Response(202);
    }
}

class AsyncApiExecutorMockPendingCorrelations implements PendingCorrelationRegistryInterface
{
    /** @var list<array{correlationId: string, executionId: string, stepId: string, channelPath: string}> */
    public array $created = [];

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void
    {
        $this->created[] = compact('correlationId', 'executionId', 'stepId', 'channelPath');
    }

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        return null;
    }

    public function consume(string $correlationId): void
    {
    }

    public function existsForExecution(string $executionId): bool
    {
        return false;
    }
}

class AsyncApiExecutorMockResolver implements \Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface
{
    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
    {
        return new Request('POST', 'http://broker.local/publish');
    }

    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        return [];
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }
}

function asyncApiExecutorDocument(): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), []);
}

it('supports steps with action send or receive, not steps without an action', function (): void {
    $executor = new AsyncApiStepExecutor(
        new AsyncApiExecutorMockPendingCorrelations(),
        new ExpressionEvaluator(),
        new AsyncApiExecutorMockClient(),
        new AsyncApiExecutorMockResolver(),
    );

    $plainStep = new Step('s1', null, null, null, null, [], null, [], [], [], []);
    $sendStep = new Step('s2', null, null, null, null, [], null, [], [], [], [], [], 'send');
    $receiveStep = new Step('s3', null, null, null, null, [], null, [], [], [], [], [], 'receive');

    expect($executor->supports($plainStep, asyncApiExecutorDocument()))->toBeFalse();
    expect($executor->supports($sendStep, asyncApiExecutorDocument()))->toBeTrue();
    expect($executor->supports($receiveStep, asyncApiExecutorDocument()))->toBeTrue();
});

it('publishes and resolves immediately for action send', function (): void {
    $client = new AsyncApiExecutorMockClient();
    $executor = new AsyncApiStepExecutor(
        new AsyncApiExecutorMockPendingCorrelations(),
        new ExpressionEvaluator(),
        $client,
        new AsyncApiExecutorMockResolver(),
    );

    $step = new Step('publish-ride', null, null, null, null, [], null, [], [], [], [], [], 'send');
    $context = new WorkflowContext('def_1', [], [], [], 'wf_1', 'exec_1');

    $outcome = $executor->execute($step, $context, asyncApiExecutorDocument(), 'exec_1');

    expect($outcome->suspended)->toBeFalse();
    expect($outcome->statusCode)->toBe(202);
    expect($client->lastRequest)->not->toBeNull();
});

it('writes a PendingCorrelation and suspends for action receive', function (): void {
    $pendingCorrelations = new AsyncApiExecutorMockPendingCorrelations();
    $executor = new AsyncApiStepExecutor(
        $pendingCorrelations,
        new ExpressionEvaluator(),
        new AsyncApiExecutorMockClient(),
        new AsyncApiExecutorMockResolver(),
    );

    $step = new Step(
        'wait-for-ride', null, null, null, null, [], null, [], [], [], [], [],
        'receive', 'channels/rides/created', new Expression('{$inputs.correlationId}'),
    );
    $context = new WorkflowContext('def_1', ['correlationId' => 'corr_abc'], [], [], 'wf_1', 'exec_1');

    $outcome = $executor->execute($step, $context, asyncApiExecutorDocument(), 'exec_1');

    expect($outcome->suspended)->toBeTrue();
    expect($pendingCorrelations->created)->toHaveCount(1);
    expect($pendingCorrelations->created[0]['correlationId'])->toBe('corr_abc');
    expect($pendingCorrelations->created[0]['executionId'])->toBe('exec_1');
    expect($pendingCorrelations->created[0]['stepId'])->toBe('wait-for-ride');
    expect($pendingCorrelations->created[0]['channelPath'])->toBe('channels/rides/created');
});

it('throws when a receive step has no correlationId expression', function (): void {
    $executor = new AsyncApiStepExecutor(
        new AsyncApiExecutorMockPendingCorrelations(),
        new ExpressionEvaluator(),
        new AsyncApiExecutorMockClient(),
        new AsyncApiExecutorMockResolver(),
    );

    $step = new Step('wait', null, null, null, null, [], null, [], [], [], [], [], 'receive', 'channels/x', null);
    $context = new WorkflowContext('def_1', [], [], [], 'wf_1', 'exec_1');

    expect(fn () => $executor->execute($step, $context, asyncApiExecutorDocument(), 'exec_1'))
        ->toThrow(\LogicException::class);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/AsyncApiStepExecutorTest.php`
Expected: FAIL — `AsyncApiStepExecutor` doesn't exist.

- [ ] **Step 3: Implement `AsyncApiStepExecutor`**

Create `src/Execution/AsyncApiStepExecutor.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\StepProtocolExecutorInterface;
use LogicException;

final class AsyncApiStepExecutor implements StepProtocolExecutorInterface
{
    public function __construct(
        private PendingCorrelationRegistryInterface $pendingCorrelations,
        private ExpressionEvaluator $evaluator,
        private HttpClientInterface $httpClient,
        private ExpressionResolverInterface $expressionResolver,
    ) {
    }

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return $step->action !== null;
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        if ($step->action === 'send') {
            $request = $this->expressionResolver->compileRequest($step, $context, $document);
            $response = $this->httpClient->sendRequest($request);

            return StepExecutionOutcome::resolved($response->getStatusCode(), [], []);
        }

        if ($step->correlationId === null) {
            throw new LogicException("Step '{$step->stepId}' has action 'receive' but no correlationId expression.");
        }
        if ($step->channelPath === null) {
            throw new LogicException("Step '{$step->stepId}' has action 'receive' but no channelPath.");
        }

        $correlationId = (string) $this->evaluator->evaluate($step->correlationId, $context, $step->stepId);

        $this->pendingCorrelations->create($correlationId, $executionId, $step->stepId, $step->channelPath);

        return StepExecutionOutcome::suspended();
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/AsyncApiStepExecutorTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Execution/AsyncApiStepExecutor.php tests/Execution/AsyncApiStepExecutorTest.php
git commit -m "feat: add AsyncApiStepExecutor for action:send/receive steps"
```

---

## Task 13: `StepExecutionWorker` — execution-scoped lock, reload-before-evaluate, protocol dispatch, `StepOutcomeHandler` call

**Files:**
- Modify: `src/Execution/StepExecutionWorker.php`
- Modify: `tests/Execution/StepExecutionWorkerTest.php` (relocated from `tests/Unit/Execution/StepExecutionWorkerTest.php`, which doc 02's plan creates)

**Interfaces:**
- Consumes: everything from Tasks 1-9.
- Produces: `StepExecutionWorker`'s constructor drops `Engine`/`HttpClientInterface` (moved to `StepOutcomeHandler`/`HttpStepExecutor`) and gains `array $protocolExecutors` (`list<StepProtocolExecutorInterface>`) and `StepOutcomeHandler $outcomeHandler`. Full new signature in Step 4 below.
- This is the task where the diamond/fan-in fix and the ordering-bug fix actually land in the worker: lock key is `execution_lock_{executionId}` (was `workflow_lock_{definitionId}`), and persisted state is reloaded and merged with the job's own context *before* the idempotency check and before any dispatch decision.

- [ ] **Step 1: Relocate the doc-02 test file**

```bash
git mv tests/Unit/Execution/StepExecutionWorkerTest.php tests/Execution/StepExecutionWorkerTest.php
```

If doc 02 placed it elsewhere, `find tests -iname StepExecutionWorkerTest.php` first.

- [ ] **Step 2: Write the failing tests**

Replace the full contents of `tests/Execution/StepExecutionWorkerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Contracts\StepProtocolExecutorInterface;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\ExecutionStatus;
use Alama\LaravelArazzo\Execution\InMemoryDefinitionRegistry;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Execution\PendingCorrelation;
use Alama\LaravelArazzo\Execution\StepExecutionOutcome;
use Alama\LaravelArazzo\Execution\StepExecutionWorker;
use Alama\LaravelArazzo\Execution\StepOutcomeHandler;
use Alama\LaravelArazzo\Execution\StepStatus;
use Alama\LaravelArazzo\Execution\SyncQueueDriver;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Psr\Http\Message\RequestInterface;

class WorkerMockLockManager implements LockManagerInterface
{
    public int $acquireCount = 0;

    /** @var list<string> */
    public array $keysUsed = [];

    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $this->acquireCount++;
        $this->keysUsed[] = $key;

        return $callback();
    }
}

class WorkerMockStateStore implements StateStoreInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $saves = [];
    /** @var array<string, int|null> */
    public array $ttls = [];
    /** @var array<string, array<string, mixed>> */
    public array $preloaded = [];

    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
        $this->saves[$executionId] = $state;
        $this->ttls[$executionId] = $ttlSeconds;
    }

    public function load(string $executionId): ?array
    {
        return $this->preloaded[$executionId] ?? null;
    }
}

class WorkerMockExpressionResolver implements ExpressionResolverInterface
{
    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
    {
        throw new \LogicException('not used -- protocol dispatch is faked directly in these tests');
    }

    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        return [];
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return $criteria === [];
    }
}

class WorkerMockEventLedger implements EventLedgerInterface
{
    /** @var list<array{executionId: string, eventType: string, payload: array<string, mixed>}> */
    public array $appended = [];

    public function append(string $executionId, string $eventType, array $payload): void
    {
        $this->appended[] = ['executionId' => $executionId, 'eventType' => $eventType, 'payload' => $payload];
    }
}

class WorkerMockExecutionRegistry implements ExecutionRegistryInterface
{
    /** @var list<array{executionId: string, definitionId: string, workflowId: string}> */
    public array $started = [];
    /** @var list<array{executionId: string, status: ExecutionStatus}> */
    public array $completed = [];

    public function start(string $executionId, string $definitionId, string $workflowId): void
    {
        $this->started[] = ['executionId' => $executionId, 'definitionId' => $definitionId, 'workflowId' => $workflowId];
    }

    public function complete(string $executionId, ExecutionStatus $status): void
    {
        $this->completed[] = ['executionId' => $executionId, 'status' => $status];
    }
}

class WorkerMockPendingCorrelationRegistry implements PendingCorrelationRegistryInterface
{
    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void
    {
    }

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        return null;
    }

    public function consume(string $correlationId): void
    {
    }

    public function existsForExecution(string $executionId): bool
    {
        return false;
    }
}

class WorkerFakeProtocolExecutor implements StepProtocolExecutorInterface
{
    public function __construct(private StepExecutionOutcome $outcome)
    {
    }

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return true;
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        return $this->outcome;
    }
}

function makeWorkerDocument(Workflow $workflow): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [$workflow],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

/**
 * @return array{0: StepExecutionWorker, 1: WorkerMockLockManager, 2: WorkerMockStateStore, 3: WorkerMockEventLedger, 4: WorkerMockExecutionRegistry, 5: SyncQueueDriver}
 */
function makeWorker(StepExecutionOutcome $outcome, \Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface $definitionRegistry): array
{
    $lockManager = new WorkerMockLockManager();
    $store = new WorkerMockStateStore();
    $eventLedger = new WorkerMockEventLedger();
    $executionRegistry = new WorkerMockExecutionRegistry();
    $resolver = new WorkerMockExpressionResolver();
    $queue = new SyncQueueDriver();
    $dependencyAnalyzer = new DependencyAnalyzer();
    $engine = new Engine($dependencyAnalyzer, $queue, $store);
    $outcomeHandler = new StepOutcomeHandler(
        $queue, $engine, $dependencyAnalyzer, $executionRegistry, $eventLedger,
        new WorkerMockPendingCorrelationRegistry(), $resolver,
    );

    $worker = new StepExecutionWorker(
        $lockManager, $store, $definitionRegistry, $eventLedger, $executionRegistry, $resolver,
        [new WorkerFakeProtocolExecutor($outcome)], $outcomeHandler,
    );

    return [$worker, $lockManager, $store, $eventLedger, $executionRegistry, $queue];
}

it('skips a step already at Succeeded status', function (): void {
    [$worker, $lockManager, $store, $eventLedger] = makeWorker(
        StepExecutionOutcome::resolved(200, [], []),
        new InMemoryDefinitionRegistry(),
    );

    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $context = (new WorkflowContext('def_1'))
        ->withExecutionId('exec_1')
        ->withStepResult('A', ['success' => true])
        ->withStepStatus('A', StepStatus::Succeeded);

    $worker->handle(new ExecuteStepJob($step, $context));

    expect($lockManager->acquireCount)->toBe(1);
    expect($store->saves)->toBeEmpty();
    expect($eventLedger->appended)->toBeEmpty();
});

it('throws when the context has no executionId', function (): void {
    [$worker] = makeWorker(StepExecutionOutcome::resolved(200, [], []), new InMemoryDefinitionRegistry());

    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $context = new WorkflowContext('def_1');

    expect(fn () => $worker->handle(new ExecuteStepJob($step, $context)))->toThrow(\LogicException::class);
});

it('appends a definition_missing event when the registry returns null', function (): void {
    [$worker, , $store, $eventLedger] = makeWorker(
        StepExecutionOutcome::resolved(200, [], []),
        new InMemoryDefinitionRegistry(),
    );

    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $context = (new WorkflowContext('missing_def'))->withExecutionId('exec_1');

    $worker->handle(new ExecuteStepJob($step, $context));

    expect($eventLedger->appended)->toHaveCount(1);
    expect($eventLedger->appended[0]['eventType'])->toBe('execution.definition_missing');
    expect($store->saves)->toBeEmpty();
});

it('appends a workflow_missing event when the context workflowId is not in the document', function (): void {
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $workflow = new Workflow('wf_1', null, null, null, [], [], [], [], [], []);
    $document = makeWorkerDocument($workflow);
    $definitionId = $definitionRegistry->register($document);

    [$worker, , , $eventLedger] = makeWorker(StepExecutionOutcome::resolved(200, [], []), $definitionRegistry);

    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $context = (new WorkflowContext($definitionId))->withExecutionId('exec_1')->withWorkflowId('wf_does_not_exist');

    $worker->handle(new ExecuteStepJob($step, $context));

    expect($eventLedger->appended[0]['eventType'])->toBe('execution.workflow_missing');
});

it('executes a step, saves state with TTL, appends step.executed, starts the execution, and continues via StepOutcomeHandler', function (): void {
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $stepA = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $stepB = new Step('B', null, null, null, null, [], null, [], [], [], [], ['A']);
    $workflow = new Workflow('wf_1', null, null, null, [], [$stepA, $stepB], [], [], [], []);
    $document = makeWorkerDocument($workflow);
    $definitionId = $definitionRegistry->register($document);

    [$worker, , $store, $eventLedger, $executionRegistry, $queue] = makeWorker(
        StepExecutionOutcome::resolved(200, ['id' => 1], ['id' => 1]),
        $definitionRegistry,
    );

    $context = (new WorkflowContext($definitionId))->withExecutionId('exec_1')->withWorkflowId('wf_1');

    $worker->handle(new ExecuteStepJob($stepA, $context));

    expect($store->saves)->toHaveKey('exec_1');
    expect($store->saves['exec_1']['steps'])->toHaveKey('A');
    expect($eventLedger->appended[0]['eventType'])->toBe('step.executed');
    expect($executionRegistry->started)->toHaveCount(1);
    expect($queue->dispatched)->toHaveCount(1);
    expect($queue->dispatched[0]['job']->step->stepId)->toBe('B');
});

it('suspends when the protocol executor returns a suspended outcome, without invoking StepOutcomeHandler', function (): void {
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = makeWorkerDocument($workflow);
    $definitionId = $definitionRegistry->register($document);

    [$worker, , $store, $eventLedger, , $queue] = makeWorker(StepExecutionOutcome::suspended(), $definitionRegistry);

    $context = (new WorkflowContext($definitionId))->withExecutionId('exec_1')->withWorkflowId('wf_1');

    $worker->handle(new ExecuteStepJob($step, $context));

    expect($store->saves['exec_1']['steps']['A']['status'])->toBe('suspended');
    expect($eventLedger->appended[0]['eventType'])->toBe('step.suspended');
    expect($queue->dispatched)->toBeEmpty(); // StepOutcomeHandler never called, so no choreography dispatch
});

it('reloads and merges persisted state before evaluating, so a concurrently-completed sibling step is not lost', function (): void {
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $stepA = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $stepB = new Step('B', null, null, null, null, [], null, [], [], [], []);
    $stepD = new Step('D', null, null, null, null, [], null, [], [], [], [], ['A', 'B']);
    $workflow = new Workflow('wf_1', null, null, null, [], [$stepA, $stepB, $stepD], [], [], [], []);
    $document = makeWorkerDocument($workflow);
    $definitionId = $definitionRegistry->register($document);

    [$worker, , $store, , , $queue] = makeWorker(
        StepExecutionOutcome::resolved(200, [], []),
        $definitionRegistry,
    );

    // Simulate step A already completed and persisted by a concurrent worker before this
    // job (for step B) is handled.
    $store->preloaded['exec_1'] = [
        'definitionId' => $definitionId,
        'workflowId' => 'wf_1',
        'steps' => ['A' => ['statusCode' => 200, 'status' => 'succeeded']],
        'inputs' => [],
        'components' => [],
    ];

    $context = (new WorkflowContext($definitionId))->withExecutionId('exec_1')->withWorkflowId('wf_1');

    $worker->handle(new ExecuteStepJob($stepB, $context));

    // D depends on both A and B; A came from the reloaded persisted state, B from this job.
    expect($store->saves['exec_1']['steps'])->toHaveKeys(['A', 'B']);
    expect($queue->dispatched)->toHaveCount(1);
    expect($queue->dispatched[0]['job']->step->stepId)->toBe('D');
});

it('acquires the lock using an execution-scoped key, not a definition-scoped key', function (): void {
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = makeWorkerDocument($workflow);
    $definitionId = $definitionRegistry->register($document);

    [$worker, $lockManager] = makeWorker(StepExecutionOutcome::resolved(200, [], []), $definitionRegistry);

    $context = (new WorkflowContext($definitionId))->withExecutionId('exec_42')->withWorkflowId('wf_1');

    $worker->handle(new ExecuteStepJob($step, $context));

    expect($lockManager->keysUsed[0])->toBe('execution_lock_exec_42');
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/StepExecutionWorkerTest.php`
Expected: FAIL — `StepExecutionWorker`'s constructor doesn't accept `$protocolExecutors`/`$outcomeHandler` yet, still takes `Engine`/`HttpClientInterface`.

- [ ] **Step 4: Implement the rewrite**

Replace the full contents of `src/Execution/StepExecutionWorker.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Contracts\StepProtocolExecutorInterface;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;

class StepExecutionWorker
{
    /**
     * @param list<StepProtocolExecutorInterface> $protocolExecutors
     */
    public function __construct(
        private LockManagerInterface $lockManager,
        private StateStoreInterface $stateStore,
        private DefinitionRegistryInterface $definitionRegistry,
        private EventLedgerInterface $eventLedger,
        private ExecutionRegistryInterface $executionRegistry,
        private ExpressionResolverInterface $expressionResolver,
        private array $protocolExecutors,
        private StepOutcomeHandler $outcomeHandler,
        private ?LoggerInterface $logger = null,
        private int $stateTtlSeconds = 86400,
    ) {
    }

    public function handle(ExecuteStepJob $job): void
    {
        $step = $job->step;
        $executionId = $job->context->getExecutionId();

        if ($executionId === null) {
            throw new LogicException(
                "ExecuteStepJob for step '{$step->stepId}' has no executionId -- the workflow run was not initialized before dispatch."
            );
        }

        $lockKey = "execution_lock_{$executionId}";

        $this->lockManager->acquire($lockKey, 30, function () use ($step, $job, $executionId) {
            $context = $this->reconcileWithPersistedState($job->context, $executionId);

            if ($context->getStepStatus($step->stepId) === StepStatus::Succeeded) {
                return;
            }

            $document = $this->definitionRegistry->get($context->getDefinitionId());
            if ($document === null) {
                $this->eventLedger->append($executionId, 'execution.definition_missing', [
                    'definitionId' => $context->getDefinitionId(),
                ]);

                return;
            }

            $workflow = $this->findWorkflow($document, $context->getWorkflowId());
            if ($workflow === null) {
                $this->eventLedger->append($executionId, 'execution.workflow_missing', [
                    'workflowId' => $context->getWorkflowId(),
                ]);

                return;
            }

            $executor = $this->findExecutor($step, $document);
            if ($executor === null) {
                throw new LogicException("No StepProtocolExecutorInterface supports step '{$step->stepId}'.");
            }

            $outcome = $executor->execute($step, $context, $document, $executionId);

            if ($outcome->suspended) {
                $newContext = $context->withStepStatus($step->stepId, StepStatus::Suspended);
                $this->stateStore->save($executionId, $this->serialize($newContext), $this->stateTtlSeconds);
                $this->executionRegistry->start($executionId, $newContext->getDefinitionId(), $workflow->workflowId);
                $this->eventLedger->append($executionId, 'step.suspended', ['stepId' => $step->stepId]);

                return;
            }

            $contextWithResult = $context->withStepResult($step->stepId, [
                'statusCode' => $outcome->statusCode,
                'response' => ['statusCode' => $outcome->statusCode, 'body' => $outcome->responseBody],
                'outputs' => $outcome->outputs,
            ]);

            $criteriaMet = $this->expressionResolver->evaluateSuccessCriteria($step, $contextWithResult, $document);

            $this->stateStore->save($executionId, $this->serialize($contextWithResult), $this->stateTtlSeconds);
            $this->executionRegistry->start($executionId, $contextWithResult->getDefinitionId(), $workflow->workflowId);

            try {
                $this->eventLedger->append($executionId, 'step.executed', [
                    'stepId' => $step->stepId,
                    'statusCode' => $outcome->statusCode,
                    'outputs' => $outcome->outputs,
                    'criteriaMet' => $criteriaMet,
                ]);
            } catch (Throwable $e) {
                $this->logger?->warning("Failed to append event ledger entry for step '{$step->stepId}': {$e->getMessage()}");
            }

            $this->outcomeHandler->handle($document, $workflow, $step, $contextWithResult, $executionId, $criteriaMet);
        });
    }

    private function reconcileWithPersistedState(WorkflowContext $context, string $executionId): WorkflowContext
    {
        $persisted = $this->stateStore->load($executionId);
        if ($persisted === null) {
            return $context;
        }

        $mergedSteps = array_merge($persisted['steps'] ?? [], $context->getSteps());

        return new WorkflowContext(
            $context->getDefinitionId(),
            $context->getInputs(),
            $mergedSteps,
            $context->getComponents(),
            $context->getWorkflowId(),
            $executionId,
        );
    }

    private function findWorkflow(ArazzoDocument $document, ?string $workflowId): ?Workflow
    {
        foreach ($document->workflows as $workflow) {
            if ($workflow->workflowId === $workflowId) {
                return $workflow;
            }
        }

        return null;
    }

    private function findExecutor(Step $step, ArazzoDocument $document): ?StepProtocolExecutorInterface
    {
        foreach ($this->protocolExecutors as $executor) {
            if ($executor->supports($step, $document)) {
                return $executor;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(WorkflowContext $context): array
    {
        return [
            'definitionId' => $context->getDefinitionId(),
            'workflowId' => $context->getWorkflowId(),
            'steps' => $context->getSteps(),
            'inputs' => $context->getInputs(),
            'components' => $context->getComponents(),
        ];
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/StepExecutionWorkerTest.php`
Expected: PASS (8 tests)

- [ ] **Step 6: Commit**

```bash
git add src/Execution/StepExecutionWorker.php tests/Execution/StepExecutionWorkerTest.php
git commit -m "fix: rekey StepExecutionWorker lock to executionId, reload state before evaluating, dispatch via StepProtocolExecutorInterface and StepOutcomeHandler"
```

---

## Task 14: `RunExecuteStepJob` (real `ShouldQueue`) + `LaravelQueueDriver` wraps it

**Files:**
- Create: `src/Laravel/Jobs/RunExecuteStepJob.php`
- Modify: `src/Laravel/LaravelQueueDriver.php`
- Test: `tests/Laravel/LaravelQueueDriverTest.php`
- Test: `tests/Laravel/Jobs/RunExecuteStepJobTest.php`

**Interfaces:**
- Produces: `RunExecuteStepJob implements ShouldQueue` wrapping a plain `ExecuteStepJob`, `handle(StepExecutionWorker $worker)` delegates to `$worker->handle($this->inner)`. `LaravelQueueDriver::dispatch()` wraps any `ExecuteStepJob` payload before pushing. Fixes the gap flagged in doc 03's "Existing code" note — today `Queue::later()`/`Queue::push()` are called with a plain, non-`ShouldQueue` object, which does not actually dispatch correctly against a real Laravel queue connection.

- [ ] **Step 1: Write the failing tests**

Create `tests/Laravel/Jobs/RunExecuteStepJobTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Laravel\Jobs;

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Execution\StepExecutionWorker;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Laravel\Jobs\RunExecuteStepJob;
use Alama\LaravelArazzo\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

uses(TestCase::class);

class RecordingStepExecutionWorker extends StepExecutionWorker
{
    /** @var list<ExecuteStepJob> */
    public array $handled = [];

    public function __construct()
    {
    }

    public function handle(ExecuteStepJob $job): void
    {
        $this->handled[] = $job;
    }
}

it('round-trips ExecuteStepJob through a real Laravel queue connection and reaches StepExecutionWorker::handle()', function (): void {
    config(['queue.default' => 'sync']);

    $recorder = new RecordingStepExecutionWorker();
    $this->app->instance(StepExecutionWorker::class, $recorder);

    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $context = (new WorkflowContext('def_1'))->withExecutionId('exec_1');
    $innerJob = new ExecuteStepJob($step, $context);

    Queue::connection('sync')->push(new RunExecuteStepJob($innerJob));

    expect($recorder->handled)->toHaveCount(1);
    expect($recorder->handled[0]->step->stepId)->toBe('A');
    expect($recorder->handled[0]->context->getExecutionId())->toBe('exec_1');
    // A genuinely different instance -- confirms it went through real serialize/unserialize,
    // not just an in-memory closure call.
    expect($recorder->handled[0])->not->toBe($innerJob);
});
```

Create `tests/Laravel/LaravelQueueDriverTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Laravel;

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Laravel\Jobs\RunExecuteStepJob;
use Alama\LaravelArazzo\Laravel\LaravelQueueDriver;
use Alama\LaravelArazzo\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

uses(TestCase::class);

it('wraps ExecuteStepJob in RunExecuteStepJob and pushes immediately when no delay is given', function (): void {
    Queue::fake();

    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $job = new ExecuteStepJob($step, new WorkflowContext('def_1'));

    (new LaravelQueueDriver())->dispatch($job);

    Queue::assertPushed(RunExecuteStepJob::class, fn (RunExecuteStepJob $pushed) => $pushed->inner->step->stepId === 'A');
});

it('wraps and dispatches via later() when a delay is given', function (): void {
    Queue::fake();

    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $job = new ExecuteStepJob($step, new WorkflowContext('def_1'));

    (new LaravelQueueDriver())->dispatch($job, 30);

    Queue::assertPushed(RunExecuteStepJob::class);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Laravel/Jobs/RunExecuteStepJobTest.php tests/Laravel/LaravelQueueDriverTest.php`
Expected: FAIL — `RunExecuteStepJob` doesn't exist; `LaravelQueueDriver::dispatch()` pushes the raw `ExecuteStepJob`, not a wrapper.

- [ ] **Step 3: Implement `RunExecuteStepJob`**

Create `src/Laravel/Jobs/RunExecuteStepJob.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel\Jobs;

use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Execution\StepExecutionWorker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunExecuteStepJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public ExecuteStepJob $inner)
    {
    }

    public function handle(StepExecutionWorker $worker): void
    {
        $worker->handle($this->inner);
    }
}
```

- [ ] **Step 4: Update `LaravelQueueDriver`**

Replace the full contents of `src/Laravel/LaravelQueueDriver.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Laravel\Jobs\RunExecuteStepJob;
use Illuminate\Support\Facades\Queue;

class LaravelQueueDriver implements QueueDriverInterface
{
    public function dispatch(object $job, int $delaySeconds = 0): void
    {
        $wrapped = $job instanceof ExecuteStepJob ? new RunExecuteStepJob($job) : $job;

        if ($delaySeconds > 0) {
            Queue::later(now()->addSeconds($delaySeconds), $wrapped);
        } else {
            Queue::push($wrapped);
        }
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Laravel/Jobs/RunExecuteStepJobTest.php tests/Laravel/LaravelQueueDriverTest.php`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add src/Laravel/Jobs/RunExecuteStepJob.php src/Laravel/LaravelQueueDriver.php tests/Laravel/Jobs/RunExecuteStepJobTest.php tests/Laravel/LaravelQueueDriverTest.php
git commit -m "fix: wrap ExecuteStepJob in a real ShouldQueue job so delayed retries actually dispatch"
```

---

## Task 15: `ResumeCorrelationJob` + `CorrelationResumer` + `RunResumeCorrelationJob`

**Files:**
- Create: `src/Execution/Jobs/ResumeCorrelationJob.php`
- Create: `src/Execution/CorrelationResumer.php`
- Create: `src/Laravel/Jobs/RunResumeCorrelationJob.php`
- Modify: `src/Laravel/LaravelQueueDriver.php`
- Test: `tests/Execution/CorrelationResumerTest.php`
- Test: `tests/Laravel/LaravelQueueDriverTest.php` (extend)

**Interfaces:**
- Consumes: `PendingCorrelationRegistryInterface` (Task 5), `StepOutcomeHandler` (Tasks 6-8).
- Produces: `ResumeCorrelationJob` (plain, framework-agnostic: `correlationId`, `payload`); `CorrelationResumer::resume(ResumeCorrelationJob): void` — loads the correlation, reloads persisted execution state, reconstructs document/workflow/step, merges the inbound payload as the step's response, consumes the correlation, evaluates success criteria, saves state, and calls `StepOutcomeHandler::handle()` — the same decision logic the HTTP path uses. `RunResumeCorrelationJob implements ShouldQueue` wraps it. Consumed by Task 17 (`WebhookResumeController`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Execution/CorrelationResumerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\CorrelationResumer;
use Alama\LaravelArazzo\Execution\InMemoryDefinitionRegistry;
use Alama\LaravelArazzo\Execution\Jobs\ResumeCorrelationJob;
use Alama\LaravelArazzo\Execution\PendingCorrelation;
use Alama\LaravelArazzo\Execution\StepOutcomeHandler;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Psr\Http\Message\RequestInterface;

class ResumerMockPendingCorrelations implements PendingCorrelationRegistryInterface
{
    public ?PendingCorrelation $toReturn = null;
    /** @var list<string> */
    public array $consumed = [];

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void
    {
    }

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        return $this->toReturn;
    }

    public function consume(string $correlationId): void
    {
        $this->consumed[] = $correlationId;
    }

    public function existsForExecution(string $executionId): bool
    {
        return false;
    }
}

class ResumerMockStateStore implements StateStoreInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $preloaded = [];
    /** @var array<string, array<string, mixed>> */
    public array $saves = [];

    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
        $this->saves[$executionId] = $state;
    }

    public function load(string $executionId): ?array
    {
        return $this->preloaded[$executionId] ?? null;
    }
}

class ResumerMockEventLedger implements EventLedgerInterface
{
    /** @var list<array{executionId: string, eventType: string, payload: array<string, mixed>}> */
    public array $appended = [];

    public function append(string $executionId, string $eventType, array $payload): void
    {
        $this->appended[] = ['executionId' => $executionId, 'eventType' => $eventType, 'payload' => $payload];
    }
}

class ResumerMockExpressionResolver implements ExpressionResolverInterface
{
    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
    {
        throw new \LogicException('not used by resume');
    }

    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        return ['echo' => $context->getSteps()[$step->stepId]['response']['body'] ?? null];
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return $criteria === [];
    }
}

class RecordingStepOutcomeHandler extends StepOutcomeHandler
{
    /** @var list<array{document: ArazzoDocument, workflow: Workflow, step: Step, context: WorkflowContext, executionId: string, criteriaMet: bool}> */
    public array $calls = [];

    public function __construct()
    {
    }

    public function handle(ArazzoDocument $document, Workflow $workflow, Step $step, WorkflowContext $context, string $executionId, bool $criteriaMet): void
    {
        $this->calls[] = compact('document', 'workflow', 'step', 'context', 'executionId', 'criteriaMet');
    }
}

function resumerDocument(): array
{
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $step = new Step('wait-for-ride', null, null, null, null, [], null, [], [], [], [], [], 'receive', 'channels/rides/created');
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$workflow], new Components([], [], [], []), []);
    $definitionId = $definitionRegistry->register($document);

    return [$definitionRegistry, $definitionId, $workflow, $step];
}

it('does nothing when the correlation is not found', function (): void {
    $pendingCorrelations = new ResumerMockPendingCorrelations();
    $stateStore = new ResumerMockStateStore();
    [$definitionRegistry] = resumerDocument();
    $outcomeHandler = new RecordingStepOutcomeHandler();

    $resumer = new CorrelationResumer($pendingCorrelations, $stateStore, $definitionRegistry, new ResumerMockExpressionResolver(), $outcomeHandler, new ResumerMockEventLedger());

    $resumer->resume(new ResumeCorrelationJob('missing', ['x' => 1]));

    expect($outcomeHandler->calls)->toBeEmpty();
    expect($pendingCorrelations->consumed)->toBeEmpty();
});

it('logs and does nothing when persisted state is missing', function (): void {
    $pendingCorrelations = new ResumerMockPendingCorrelations();
    $pendingCorrelations->toReturn = new PendingCorrelation('corr_1', 'exec_1', 'wait-for-ride', 'channels/rides/created');
    $stateStore = new ResumerMockStateStore(); // nothing preloaded
    [$definitionRegistry] = resumerDocument();
    $eventLedger = new ResumerMockEventLedger();
    $outcomeHandler = new RecordingStepOutcomeHandler();

    $resumer = new CorrelationResumer($pendingCorrelations, $stateStore, $definitionRegistry, new ResumerMockExpressionResolver(), $outcomeHandler, $eventLedger);

    $resumer->resume(new ResumeCorrelationJob('corr_1', ['x' => 1]));

    expect($outcomeHandler->calls)->toBeEmpty();
    expect($eventLedger->appended[0]['eventType'])->toBe('execution.state_missing');
});

it('merges the payload, consumes the correlation, saves state, and calls StepOutcomeHandler', function (): void {
    $pendingCorrelations = new ResumerMockPendingCorrelations();
    $pendingCorrelations->toReturn = new PendingCorrelation('corr_1', 'exec_1', 'wait-for-ride', 'channels/rides/created');

    [$definitionRegistry, $definitionId, $workflow, $step] = resumerDocument();

    $stateStore = new ResumerMockStateStore();
    $stateStore->preloaded['exec_1'] = [
        'definitionId' => $definitionId,
        'workflowId' => 'wf_1',
        'steps' => [],
        'inputs' => [],
        'components' => [],
    ];

    $eventLedger = new ResumerMockEventLedger();
    $outcomeHandler = new RecordingStepOutcomeHandler();

    $resumer = new CorrelationResumer($pendingCorrelations, $stateStore, $definitionRegistry, new ResumerMockExpressionResolver(), $outcomeHandler, $eventLedger);

    $resumer->resume(new ResumeCorrelationJob('corr_1', ['rideId' => 'r_1']));

    expect($pendingCorrelations->consumed)->toBe(['corr_1']);
    expect($stateStore->saves['exec_1']['steps']['wait-for-ride']['response']['body'])->toBe(['rideId' => 'r_1']);
    expect($eventLedger->appended)->toContainEqual([
        'executionId' => 'exec_1',
        'eventType' => 'step.resumed',
        'payload' => ['stepId' => 'wait-for-ride', 'correlationId' => 'corr_1'],
    ]);

    expect($outcomeHandler->calls)->toHaveCount(1);
    expect($outcomeHandler->calls[0]['executionId'])->toBe('exec_1');
    expect($outcomeHandler->calls[0]['criteriaMet'])->toBeTrue();
    expect($outcomeHandler->calls[0]['step']->stepId)->toBe('wait-for-ride');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/CorrelationResumerTest.php`
Expected: FAIL — `CorrelationResumer`/`ResumeCorrelationJob` don't exist.

- [ ] **Step 3: Create `ResumeCorrelationJob`**

Create `src/Execution/Jobs/ResumeCorrelationJob.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Jobs;

final class ResumeCorrelationJob
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $correlationId,
        public array $payload,
    ) {
    }
}
```

- [ ] **Step 4: Implement `CorrelationResumer`**

Create `src/Execution/CorrelationResumer.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Jobs\ResumeCorrelationJob;

class CorrelationResumer
{
    public function __construct(
        private PendingCorrelationRegistryInterface $pendingCorrelations,
        private StateStoreInterface $stateStore,
        private DefinitionRegistryInterface $definitionRegistry,
        private ExpressionResolverInterface $expressionResolver,
        private StepOutcomeHandler $outcomeHandler,
        private EventLedgerInterface $eventLedger,
    ) {
    }

    public function resume(ResumeCorrelationJob $job): void
    {
        $correlation = $this->pendingCorrelations->findByCorrelationId($job->correlationId);
        if ($correlation === null) {
            return;
        }

        $executionId = $correlation->executionId;
        $persisted = $this->stateStore->load($executionId);
        if ($persisted === null) {
            $this->eventLedger->append($executionId, 'execution.state_missing', ['correlationId' => $job->correlationId]);

            return;
        }

        $document = $this->definitionRegistry->get((string) $persisted['definitionId']);
        if ($document === null) {
            $this->eventLedger->append($executionId, 'execution.definition_missing', ['definitionId' => $persisted['definitionId']]);

            return;
        }

        $workflow = $this->findWorkflow($document, (string) $persisted['workflowId']);
        if ($workflow === null) {
            $this->eventLedger->append($executionId, 'execution.workflow_missing', ['workflowId' => $persisted['workflowId']]);

            return;
        }

        $step = $this->findStep($workflow, $correlation->stepId);
        if ($step === null) {
            $this->eventLedger->append($executionId, 'execution.step_missing', ['stepId' => $correlation->stepId]);

            return;
        }

        $context = new WorkflowContext(
            (string) $persisted['definitionId'],
            (array) ($persisted['inputs'] ?? []),
            (array) ($persisted['steps'] ?? []),
            (array) ($persisted['components'] ?? []),
            (string) $persisted['workflowId'],
            $executionId,
        );

        $contextWithResponse = $context->withStepResponse($step->stepId, [
            'statusCode' => 200,
            'body' => $job->payload,
        ]);
        $outputs = $this->expressionResolver->extractOutputs($step, $contextWithResponse, $document);

        $contextWithResult = $context->withStepResult($step->stepId, [
            'statusCode' => 200,
            'response' => ['statusCode' => 200, 'body' => $job->payload],
            'outputs' => $outputs,
        ]);

        $this->pendingCorrelations->consume($job->correlationId);

        $criteriaMet = $this->expressionResolver->evaluateSuccessCriteria($step, $contextWithResult, $document);

        $this->stateStore->save($executionId, [
            'definitionId' => $contextWithResult->getDefinitionId(),
            'workflowId' => $contextWithResult->getWorkflowId(),
            'steps' => $contextWithResult->getSteps(),
            'inputs' => $contextWithResult->getInputs(),
            'components' => $contextWithResult->getComponents(),
        ]);

        $this->eventLedger->append($executionId, 'step.resumed', ['stepId' => $step->stepId, 'correlationId' => $job->correlationId]);

        $this->outcomeHandler->handle($document, $workflow, $step, $contextWithResult, $executionId, $criteriaMet);
    }

    private function findWorkflow(ArazzoDocument $document, string $workflowId): ?Workflow
    {
        foreach ($document->workflows as $workflow) {
            if ($workflow->workflowId === $workflowId) {
                return $workflow;
            }
        }

        return null;
    }

    private function findStep(Workflow $workflow, string $stepId): ?Step
    {
        foreach ($workflow->steps as $step) {
            if ($step->stepId === $stepId) {
                return $step;
            }
        }

        return null;
    }
}
```

- [ ] **Step 5: Create `RunResumeCorrelationJob`**

Create `src/Laravel/Jobs/RunResumeCorrelationJob.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel\Jobs;

use Alama\LaravelArazzo\Execution\CorrelationResumer;
use Alama\LaravelArazzo\Execution\Jobs\ResumeCorrelationJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunResumeCorrelationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public ResumeCorrelationJob $inner)
    {
    }

    public function handle(CorrelationResumer $resumer): void
    {
        $resumer->resume($this->inner);
    }
}
```

- [ ] **Step 6: Extend `LaravelQueueDriver` to wrap both job kinds**

Replace the full contents of `src/Laravel/LaravelQueueDriver.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Execution\Jobs\ResumeCorrelationJob;
use Alama\LaravelArazzo\Laravel\Jobs\RunExecuteStepJob;
use Alama\LaravelArazzo\Laravel\Jobs\RunResumeCorrelationJob;
use Illuminate\Support\Facades\Queue;

class LaravelQueueDriver implements QueueDriverInterface
{
    public function dispatch(object $job, int $delaySeconds = 0): void
    {
        $wrapped = match (true) {
            $job instanceof ExecuteStepJob => new RunExecuteStepJob($job),
            $job instanceof ResumeCorrelationJob => new RunResumeCorrelationJob($job),
            default => $job,
        };

        if ($delaySeconds > 0) {
            Queue::later(now()->addSeconds($delaySeconds), $wrapped);
        } else {
            Queue::push($wrapped);
        }
    }
}
```

- [ ] **Step 7: Add a wrap-test for the resume job kind**

Add this test to `tests/Laravel/LaravelQueueDriverTest.php`:

```php
it('wraps ResumeCorrelationJob in RunResumeCorrelationJob', function (): void {
    Queue::fake();

    (new LaravelQueueDriver())->dispatch(new \Alama\LaravelArazzo\Execution\Jobs\ResumeCorrelationJob('corr_1', ['x' => 1]));

    Queue::assertPushed(\Alama\LaravelArazzo\Laravel\Jobs\RunResumeCorrelationJob::class, fn ($pushed) => $pushed->inner->correlationId === 'corr_1');
});
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/CorrelationResumerTest.php tests/Laravel/LaravelQueueDriverTest.php`
Expected: PASS (4 + 3 tests)

- [ ] **Step 9: Commit**

```bash
git add src/Execution/Jobs/ResumeCorrelationJob.php src/Execution/CorrelationResumer.php src/Laravel/Jobs/RunResumeCorrelationJob.php src/Laravel/LaravelQueueDriver.php tests/Execution/CorrelationResumerTest.php tests/Laravel/LaravelQueueDriverTest.php
git commit -m "feat: add CorrelationResumer and RunResumeCorrelationJob for the AsyncAPI resume path"
```

---

## Task 16: `Psr18HttpClient` adapter + config keys + full service provider wiring

**Files:**
- Create: `src/Laravel/Psr18HttpClient.php`
- Modify: `config/arazzo.php`
- Modify: `src/LaravelArazzoServiceProvider.php`
- Modify: `tests/LaravelArazzoServiceProviderBindingsTest.php`
- Test: `tests/Laravel/Psr18HttpClientTest.php`

**Interfaces:**
- Produces: `Psr18HttpClient implements HttpClientInterface`, adapting the already-bound PSR-18 `ClientInterface` (Guzzle) — `HttpClientInterface` was never bound anywhere in the provider (flagged as scaffolding-gap in doc 02's plan alongside `LockManagerInterface`/`Engine`/`QueueDriverInterface`, which this task also finally binds). Every container binding this whole plan's classes need (`LockManagerInterface`, `QueueDriverInterface`, `Engine`, `HttpClientInterface`, `PendingCorrelationRegistryInterface`, `StepOutcomeHandler`, `HttpStepExecutor`, `AsyncApiStepExecutor`, `CorrelationResumer`, `StepExecutionWorker`) becomes resolvable from the container. `AsyncApiSourceParser` is added to `DefaultSourceResolver`'s parser map. New config keys: `arazzo.max_retry_attempts` (default `10`), `arazzo.pending_correlations_table` (default `arazzo_pending_correlations`). `hasMigrations()` gains this plan's two migrations.

- [ ] **Step 1: Write the failing test for the adapter**

Create `tests/Laravel/Psr18HttpClientTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Laravel;

use Alama\LaravelArazzo\Laravel\Psr18HttpClient;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Psr18HttpClientMockClient implements ClientInterface
{
    public ?RequestInterface $lastRequest = null;

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        return new Response(200);
    }
}

it('delegates sendRequest to the wrapped PSR-18 client', function (): void {
    $inner = new Psr18HttpClientMockClient();
    $adapter = new Psr18HttpClient($inner);

    $request = new Request('GET', 'http://example.com');
    $response = $adapter->sendRequest($request);

    expect($inner->lastRequest)->toBe($request);
    expect($response->getStatusCode())->toBe(200);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Laravel/Psr18HttpClientTest.php`
Expected: FAIL — `Psr18HttpClient` doesn't exist.

- [ ] **Step 3: Implement the adapter**

Create `src/Laravel/Psr18HttpClient.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class Psr18HttpClient implements HttpClientInterface
{
    public function __construct(private ClientInterface $client)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->client->sendRequest($request);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Laravel/Psr18HttpClientTest.php`
Expected: PASS (1 test)

- [ ] **Step 5: Add the new config keys**

In `config/arazzo.php`, add these two keys to the returned array (alongside whatever doc 02 already added — `hot_state_ttl`, `definitions_table`, `executions_table`, `events_table` — full file shown here reflects doc 02's target plus this task's additions; if doc 02 landed with different exact key names, keep those and just add the two new ones below):

```php
<?php

declare(strict_types=1);

return [
    'openai' => [
        /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
        'api_key' => env('OPENAI_API_KEY', ''),
        /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
    ],

    'hot_state_ttl' => env('ARAZZO_HOT_STATE_TTL', 86400),
    'definitions_table' => 'arazzo_definitions',
    'executions_table' => 'arazzo_executions',
    'events_table' => 'arazzo_events',

    'pending_correlations_table' => 'arazzo_pending_correlations',
    /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
    'max_retry_attempts' => env('ARAZZO_MAX_RETRY_ATTEMPTS', 10),
];
```

- [ ] **Step 6: Wire migrations and every binding into the service provider**

Replace the full contents of `src/LaravelArazzoServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo;

use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Execution\ArazzoExpressionResolver;
use Alama\LaravelArazzo\Execution\AsyncApiStepExecutor;
use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\CorrelationResumer;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\HttpStepExecutor;
use Alama\LaravelArazzo\Execution\StepExecutionWorker;
use Alama\LaravelArazzo\Execution\StepExecutor;
use Alama\LaravelArazzo\Execution\StepOutcomeHandler;
use Alama\LaravelArazzo\Execution\WorkflowExecutor;
use Alama\LaravelArazzo\Generator\ArazzoGenerator;
use Alama\LaravelArazzo\Generator\Clients\OpenAiClient;
use Alama\LaravelArazzo\Generator\Contracts\AiClientInterface;
use Alama\LaravelArazzo\Http\Controllers\ArazzoApiController;
use Alama\LaravelArazzo\Http\Controllers\WebhookResumeController;
use Alama\LaravelArazzo\Laravel\DatabaseDefinitionRegistry;
use Alama\LaravelArazzo\Laravel\DatabaseEventLedger;
use Alama\LaravelArazzo\Laravel\DatabaseExecutionRegistry;
use Alama\LaravelArazzo\Laravel\DatabasePendingCorrelationRegistry;
use Alama\LaravelArazzo\Laravel\LaravelQueueDriver;
use Alama\LaravelArazzo\Laravel\LaravelRedisLockManager;
use Alama\LaravelArazzo\Laravel\Psr18HttpClient;
use Alama\LaravelArazzo\Laravel\RedisHotStateStore;
use Alama\LaravelArazzo\Parser\Parser;
use Alama\LaravelArazzo\Resolution\DefaultSourceResolver;
use Alama\LaravelArazzo\Resolution\Fetchers\CachedFetcher;
use Alama\LaravelArazzo\Resolution\Fetchers\HttpFetcher;
use Alama\LaravelArazzo\Resolution\Fetchers\LocalFetcher;
use Alama\LaravelArazzo\Resolution\Parsers\ArazzoSourceParser;
use Alama\LaravelArazzo\Resolution\Parsers\AsyncApiSourceParser;
use Alama\LaravelArazzo\Resolution\Parsers\OpenApiSourceParser;
use Alama\LaravelArazzo\Resolution\SourceResolver;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Facades\Route;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class LaravelArazzoServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-arazzo')
            ->hasConfigFile('arazzo')
            ->hasMigrations([
                'create_arazzo_definitions_table',
                'create_arazzo_executions_table',
                'create_arazzo_events_table',
                'add_status_to_arazzo_executions_table',
                'create_arazzo_pending_correlations_table',
            ])
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        // PSR HTTP Bindings
        $this->app->bindIf(ClientInterface::class, Client::class);
        $this->app->bindIf(RequestFactoryInterface::class, HttpFactory::class);
        $this->app->bindIf(StreamFactoryInterface::class, HttpFactory::class);

        $this->app->singleton(HttpClientInterface::class, function ($app) {
            return new Psr18HttpClient($app->make(ClientInterface::class));
        });

        // Core Resolver
        $this->app->singleton(SourceResolver::class, function () {
            return new DefaultSourceResolver(
                fetchers: [
                    'http' => new CachedFetcher(new HttpFetcher(), 3600),
                    'https' => new CachedFetcher(new HttpFetcher(), 3600),
                    'file' => new LocalFetcher(),
                ],
                parsers: [
                    SourceType::Openapi->value => new OpenApiSourceParser(),
                    SourceType::Arazzo->value => new ArazzoSourceParser(new Parser()),
                    SourceType::Asyncapi->value => new AsyncApiSourceParser(),
                ],
            );
        });

        // AI Generator
        $this->app->singleton(AiClientInterface::class, function ($app) {
            return new OpenAiClient(
                $app->make(ClientInterface::class),
                $app->make(RequestFactoryInterface::class),
                $app->make(StreamFactoryInterface::class),
                config('arazzo.openai.api_key', ''),
                config('arazzo.openai.model', 'gpt-4o'),
            );
        });

        $this->app->singleton(ArazzoGenerator::class, function ($app) {
            return new ArazzoGenerator($app->make(AiClientInterface::class));
        });

        // Workflow Execution
        $this->app->singleton(ExpressionResolverInterface::class, function ($app) {
            return new ArazzoExpressionResolver(
                $app->make(SourceResolver::class),
                $app->make(RequestFactoryInterface::class),
                new ExpressionEvaluator(),
            );
        });

        $this->app->singleton(StepExecutor::class, function ($app) {
            return new StepExecutor(
                $app->make(ClientInterface::class),
                $app->make(ExpressionResolverInterface::class),
            );
        });

        $this->app->singleton(WorkflowExecutor::class, function ($app) {
            return new WorkflowExecutor($app->make(StepExecutor::class));
        });

        // Persistence (doc 02)
        $this->app->singleton(StateStoreInterface::class, function ($app) {
            return new RedisHotStateStore(
                $app->make(RedisFactory::class),
                defaultTtlSeconds: (int) config('arazzo.hot_state_ttl', 86400),
            );
        });

        $this->app->singleton(EventLedgerInterface::class, function ($app) {
            return new DatabaseEventLedger(
                $app->make('db')->connection(),
                config('arazzo.events_table', 'arazzo_events'),
                $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null,
            );
        });

        $this->app->singleton(DefinitionRegistryInterface::class, function ($app) {
            return new DatabaseDefinitionRegistry(
                $app->make('db')->connection(),
                new Parser(),
                config('arazzo.definitions_table', 'arazzo_definitions'),
            );
        });

        $this->app->singleton(ExecutionRegistryInterface::class, function ($app) {
            return new DatabaseExecutionRegistry(
                $app->make('db')->connection(),
                config('arazzo.executions_table', 'arazzo_executions'),
            );
        });

        // Queue / lock infra (doc 03 -- doc 02's plan explicitly left these bindings to this item)
        $this->app->singleton(LockManagerInterface::class, LaravelRedisLockManager::class);
        $this->app->singleton(QueueDriverInterface::class, LaravelQueueDriver::class);

        $this->app->singleton(Engine::class, function ($app) {
            return new Engine(
                new DependencyAnalyzer(),
                $app->make(QueueDriverInterface::class),
                $app->make(StateStoreInterface::class),
            );
        });

        // Async control flow (doc 03)
        $this->app->singleton(PendingCorrelationRegistryInterface::class, function ($app) {
            return new DatabasePendingCorrelationRegistry(
                $app->make('db')->connection(),
                config('arazzo.pending_correlations_table', 'arazzo_pending_correlations'),
            );
        });

        $this->app->singleton(StepOutcomeHandler::class, function ($app) {
            return new StepOutcomeHandler(
                $app->make(QueueDriverInterface::class),
                $app->make(Engine::class),
                new DependencyAnalyzer(),
                $app->make(ExecutionRegistryInterface::class),
                $app->make(EventLedgerInterface::class),
                $app->make(PendingCorrelationRegistryInterface::class),
                $app->make(ExpressionResolverInterface::class),
                (int) config('arazzo.max_retry_attempts', 10),
            );
        });

        $this->app->singleton(HttpStepExecutor::class, function ($app) {
            return new HttpStepExecutor(
                $app->make(HttpClientInterface::class),
                $app->make(ExpressionResolverInterface::class),
            );
        });

        $this->app->singleton(AsyncApiStepExecutor::class, function ($app) {
            return new AsyncApiStepExecutor(
                $app->make(PendingCorrelationRegistryInterface::class),
                new ExpressionEvaluator(),
                $app->make(HttpClientInterface::class),
                $app->make(ExpressionResolverInterface::class),
            );
        });

        $this->app->singleton(CorrelationResumer::class, function ($app) {
            return new CorrelationResumer(
                $app->make(PendingCorrelationRegistryInterface::class),
                $app->make(StateStoreInterface::class),
                $app->make(DefinitionRegistryInterface::class),
                $app->make(ExpressionResolverInterface::class),
                $app->make(StepOutcomeHandler::class),
                $app->make(EventLedgerInterface::class),
            );
        });

        $this->app->singleton(StepExecutionWorker::class, function ($app) {
            return new StepExecutionWorker(
                $app->make(LockManagerInterface::class),
                $app->make(StateStoreInterface::class),
                $app->make(DefinitionRegistryInterface::class),
                $app->make(EventLedgerInterface::class),
                $app->make(ExecutionRegistryInterface::class),
                $app->make(ExpressionResolverInterface::class),
                [
                    $app->make(HttpStepExecutor::class),
                    $app->make(AsyncApiStepExecutor::class),
                ],
                $app->make(StepOutcomeHandler::class),
            );
        });
    }

    public function packageBooted(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'arazzo');

        Route::get('/arazzo-builder', function () {
            /** @var view-string $view */
            $view = 'arazzo::arazzo';

            return view($view);
        })->middleware('web');

        Route::prefix('api/arazzo')
            ->middleware('api')
            ->group(function () {
                Route::get('/endpoints', [ArazzoApiController::class, 'endpoints']);
                Route::post('/generate', [ArazzoApiController::class, 'generate']);
                Route::post('/webhooks/{correlationId}', [WebhookResumeController::class, 'resume']);
            });
    }
}
```

Note: `WebhookResumeController` doesn't exist until Task 17 — this file won't compile/autoload cleanly until then. That's fine and expected (same cross-task ordering doc 02's plan used); come back and re-verify this task's tests after Task 17 lands, or do Task 17 immediately after this one.

- [ ] **Step 7: Extend the bindings test**

In `tests/LaravelArazzoServiceProviderBindingsTest.php`, add these imports alongside the existing ones:

```php
use Alama\LaravelArazzo\Execution\AsyncApiStepExecutor;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\CorrelationResumer;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\HttpStepExecutor;
use Alama\LaravelArazzo\Execution\StepExecutionWorker;
use Alama\LaravelArazzo\Execution\StepOutcomeHandler;
use Alama\LaravelArazzo\Laravel\DatabasePendingCorrelationRegistry;
use Alama\LaravelArazzo\Laravel\LaravelQueueDriver;
use Alama\LaravelArazzo\Laravel\LaravelRedisLockManager;
use Alama\LaravelArazzo\Laravel\Psr18HttpClient;
```

Add these tests:

```php
it('binds the queue/lock/http infra', function () {
    expect(app(HttpClientInterface::class))->toBeInstanceOf(Psr18HttpClient::class);
    expect(app(LockManagerInterface::class))->toBeInstanceOf(LaravelRedisLockManager::class);
    expect(app(QueueDriverInterface::class))->toBeInstanceOf(LaravelQueueDriver::class);
    expect(app(Engine::class))->toBeInstanceOf(Engine::class);
});

it('binds the async control flow classes', function () {
    expect(app(PendingCorrelationRegistryInterface::class))->toBeInstanceOf(DatabasePendingCorrelationRegistry::class);
    expect(app(StepOutcomeHandler::class))->toBeInstanceOf(StepOutcomeHandler::class);
    expect(app(HttpStepExecutor::class))->toBeInstanceOf(HttpStepExecutor::class);
    expect(app(AsyncApiStepExecutor::class))->toBeInstanceOf(AsyncApiStepExecutor::class);
    expect(app(CorrelationResumer::class))->toBeInstanceOf(CorrelationResumer::class);
    expect(app(StepExecutionWorker::class))->toBeInstanceOf(StepExecutionWorker::class);
});
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `vendor/bin/pest tests/LaravelArazzoServiceProviderBindingsTest.php` (after Task 17 lands — this file needs `WebhookResumeController` to exist to even boot the provider)
Expected: PASS (all bindings tests, including the 2 new ones). If `app(StateStoreInterface::class)`/Redis-dependent bindings fail to resolve in the test environment, that's a pre-existing Task-12-of-doc-02 concern (its own bindings test note already flags this), not something to weaken here.

- [ ] **Step 9: Commit**

```bash
git add src/Laravel/Psr18HttpClient.php config/arazzo.php src/LaravelArazzoServiceProvider.php tests/LaravelArazzoServiceProviderBindingsTest.php tests/Laravel/Psr18HttpClientTest.php
git commit -m "feat: wire queue/lock/http infra and async control flow classes into the service provider"
```

---

## Task 17: `WebhookResumeController`

**Files:**
- Create: `src/Http/Controllers/WebhookResumeController.php`
- Test: `tests/Http/Controllers/WebhookResumeControllerTest.php`

**Interfaces:**
- Consumes: `PendingCorrelationRegistryInterface` (Task 5), `QueueDriverInterface` (existing), `ResumeCorrelationJob` (Task 15). Route already registered in Task 16's `packageBooted()`: `POST /api/arazzo/webhooks/{correlationId}`.
- Produces: `404` when the correlation is unknown/already consumed (no state mutation — safe to retry from the sender's side); `202` and a queued `ResumeCorrelationJob` on a hit — processing happens asynchronously in `CorrelationResumer`, keeping the webhook response fast and the endpoint idempotent-retriable (a second hit on an already-consumed correlation is just another 404).
- No signature verification on this route in this plan — `correlationId` unguessability is the only protection, per the design spec.

- [ ] **Step 1: Write the failing tests**

Create `tests/Http/Controllers/WebhookResumeControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Http\Controllers;

use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Jobs\ResumeCorrelationJob;
use Alama\LaravelArazzo\Execution\PendingCorrelation;
use Alama\LaravelArazzo\Laravel\Jobs\RunResumeCorrelationJob;
use Alama\LaravelArazzo\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\postJson;

uses(TestCase::class);

class WebhookControllerMockPendingCorrelations implements PendingCorrelationRegistryInterface
{
    public ?PendingCorrelation $toReturn = null;

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void
    {
    }

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        return $this->toReturn;
    }

    public function consume(string $correlationId): void
    {
    }

    public function existsForExecution(string $executionId): bool
    {
        return false;
    }
}

it('returns 404 and dispatches nothing when the correlation is unknown', function (): void {
    Queue::fake();
    $this->app->instance(PendingCorrelationRegistryInterface::class, new WebhookControllerMockPendingCorrelations());

    postJson('/api/arazzo/webhooks/unknown-corr', ['rideId' => 'r_1'])
        ->assertStatus(404);

    Queue::assertNothingPushed();
});

it('returns 202 and dispatches a ResumeCorrelationJob when the correlation is found', function (): void {
    Queue::fake();
    $fake = new WebhookControllerMockPendingCorrelations();
    $fake->toReturn = new PendingCorrelation('corr_1', 'exec_1', 'wait-for-ride', 'channels/rides/created');
    $this->app->instance(PendingCorrelationRegistryInterface::class, $fake);

    postJson('/api/arazzo/webhooks/corr_1', ['rideId' => 'r_1'])
        ->assertStatus(202);

    Queue::assertPushed(RunResumeCorrelationJob::class, function (RunResumeCorrelationJob $pushed) {
        return $pushed->inner->correlationId === 'corr_1' && $pushed->inner->payload === ['rideId' => 'r_1'];
    });
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Http/Controllers/WebhookResumeControllerTest.php`
Expected: FAIL — route/controller don't exist (404 on both, second test's `assertStatus(202)` fails).

- [ ] **Step 3: Implement the controller**

Create `src/Http/Controllers/WebhookResumeController.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Http\Controllers;

use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Jobs\ResumeCorrelationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WebhookResumeController extends Controller
{
    public function resume(
        string $correlationId,
        Request $request,
        PendingCorrelationRegistryInterface $pendingCorrelations,
        QueueDriverInterface $queueDriver,
    ): JsonResponse {
        $correlation = $pendingCorrelations->findByCorrelationId($correlationId);
        if ($correlation === null) {
            return response()->json(['error' => 'correlation not found'], 404);
        }

        $payload = (array) $request->json()->all();
        $queueDriver->dispatch(new ResumeCorrelationJob($correlationId, $payload));

        return response()->json([], 202);
    }
}
```

(The route itself — `Route::post('/api/arazzo/webhooks/{correlationId}', [WebhookResumeController::class, 'resume'])` — was already added to `packageBooted()` in Task 16.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Http/Controllers/WebhookResumeControllerTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Re-verify Task 16's bindings test now that this controller exists**

Run: `vendor/bin/pest tests/LaravelArazzoServiceProviderBindingsTest.php`
Expected: PASS (the provider now boots cleanly with `WebhookResumeController` resolvable).

- [ ] **Step 6: Commit**

```bash
git add src/Http/Controllers/WebhookResumeController.php tests/Http/Controllers/WebhookResumeControllerTest.php
git commit -m "feat: add WebhookResumeController for AsyncAPI correlation resume"
```

---

## Task 18: Fixture + AsyncAPI suspend/resume end-to-end test

**Files:**
- Create: `tests/fixtures/parser/arazzo-1.1-cross-protocol-saga.yaml`
- Modify: `tests/Http/Controllers/WebhookResumeControllerTest.php`

**Interfaces:**
- Produces the fixture file the roadmap doc references but which doesn't exist yet. Exercises the full real path: `Parser` → `DatabaseDefinitionRegistry` → `LaravelQueueDriver`/`RunExecuteStepJob` (sync) → `StepExecutionWorker` → `HttpStepExecutor` → `StepOutcomeHandler` (continues) → `AsyncApiStepExecutor` (suspends, writes `PendingCorrelation`) → `POST /api/arazzo/webhooks/{correlationId}` → `RunResumeCorrelationJob` (sync) → `CorrelationResumer` → `StepOutcomeHandler` (auto-completes).
- **Known, out-of-scope gap surfaced by this test:** `Parser::parseStep()` never reads a step-level `dependsOn` from YAML (confirmed by reading `Parser.php` — the `new Step(...)` call omits that param entirely, so it always defaults to `[]`). The fixture below still declares `dependsOn: [create-ride]` on the second step, documenting the intended target shape, but this test's actual sequencing comes from only dispatching the *first* step's job manually and letting choreography take it from there — not from parsed `dependsOn`. Wiring `dependsOn` parsing is [08 — Dynamic fan-out/fan-in](../roadmap/08-dynamic-fan-out-fan-in.md)'s job, not this plan's.

- [ ] **Step 1: Create the fixture**

Create `tests/fixtures/parser/arazzo-1.1-cross-protocol-saga.yaml`:

```yaml
arazzo: 1.0.0
info:
  title: Cross-Protocol Saga
  version: 1.0.0
sourceDescriptions: []
workflows:
  - workflowId: ride-saga
    steps:
      - stepId: create-ride
        operationPath: http://api.example.com/rides
        outputs:
          rideId: $.rideId
      - stepId: wait-for-ride-status
        action: receive
        channelPath: channels/rides/status
        correlationId: "{$steps.create-ride.response.body#/rideId}"
        dependsOn:
          - create-ride
```

- [ ] **Step 2: Write the failing test**

Add these imports to `tests/Http/Controllers/WebhookResumeControllerTest.php` (alongside the ones Task 17 added):

```php
use Alama\LaravelArazzo\Dto\Enum\Format;
use Alama\LaravelArazzo\Dto\RawDocument;
use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Loader\SymfonyYamlDecoder;
use Alama\LaravelArazzo\Parser\Parser;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
```

Append this test to the end of the file:

```php
it('runs a full HTTP -> AsyncAPI suspend/resume saga end to end via the fixture document', function (): void {
    config(['queue.default' => 'sync']);

    $this->app->instance(StateStoreInterface::class, new class implements StateStoreInterface {
        /** @var array<string, array<string, mixed>> */
        private array $store = [];

        public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
        {
            $this->store[$executionId] = $state;
        }

        public function load(string $executionId): ?array
        {
            return $this->store[$executionId] ?? null;
        }
    });

    $this->app->instance(HttpClientInterface::class, new class implements HttpClientInterface {
        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            return new Response(201, [], json_encode(['rideId' => 'r_1']));
        }
    });

    $rawYaml = file_get_contents(__DIR__ . '/../../fixtures/parser/arazzo-1.1-cross-protocol-saga.yaml');
    $decoded = (new SymfonyYamlDecoder())->decode($rawYaml);
    $document = (new Parser())->parse(new RawDocument(
        (array) $decoded,
        'file://arazzo-1.1-cross-protocol-saga.yaml',
        Format::Yaml,
    ));

    $definitionRegistry = app(DefinitionRegistryInterface::class);
    $definitionId = $definitionRegistry->register($document);

    $workflow = $document->workflows[0];
    $createRide = $workflow->steps[0];

    $context = new WorkflowContext($definitionId, [], [], [], 'ride-saga', 'exec_saga_1');

    app(QueueDriverInterface::class)->dispatch(new ExecuteStepJob($createRide, $context));

    $pendingCorrelation = DB::table('arazzo_pending_correlations')->where('execution_id', 'exec_saga_1')->first();
    expect($pendingCorrelation)->not->toBeNull();
    expect($pendingCorrelation->correlation_id)->toBe('r_1');
    expect($pendingCorrelation->step_id)->toBe('wait-for-ride-status');

    postJson('/api/arazzo/webhooks/r_1', ['status' => 'completed'])
        ->assertStatus(202);

    expect(DB::table('arazzo_pending_correlations')->where('correlation_id', 'r_1')->exists())->toBeFalse();

    $executionRow = DB::table('arazzo_executions')->where('id', 'exec_saga_1')->first();
    expect($executionRow->status)->toBe('succeeded');
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Http/Controllers/WebhookResumeControllerTest.php --filter="full HTTP"`
Expected: FAIL until every prior task has landed (this test exercises the entire chain end to end) — if you're executing tasks strictly in order, this is the first point all of Tasks 1-17 get verified working together. Debug against whichever specific assertion fails first; don't skip straight to modifying unrelated code.

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Http/Controllers/WebhookResumeControllerTest.php`
Expected: PASS (3 tests total in this file, including Task 17's 2)

- [ ] **Step 5: Commit**

```bash
git add tests/fixtures/parser/arazzo-1.1-cross-protocol-saga.yaml tests/Http/Controllers/WebhookResumeControllerTest.php
git commit -m "test: add end-to-end HTTP->AsyncAPI suspend/resume saga fixture and test"
```

---

## Task 19: Diamond fan-in regression test — two full `handle()` calls, not just one reload

**Files:**
- Modify: `tests/Execution/StepExecutionWorkerTest.php`

**Interfaces:**
- No production code changes — Task 13 already implements the fix. This task adds the regression test promised by the design spec: a full `A → {B, C} → D` diamond where **both** B's and C's jobs carry the same stale (A-only) context snapshot — the exact historical race — and both go through complete, independent `StepExecutionWorker::handle()` calls, not a single call against a pre-merged canned state (that narrower case is already covered by Task 13's "reloads and merges" test).

- [ ] **Step 1: Write the failing test**

This test only becomes meaningful once Task 13's fix is in place — if you're executing strictly in order it should already pass; if it doesn't, that's a real regression in Task 13's implementation, not a reason to weaken this test. Append to `tests/Execution/StepExecutionWorkerTest.php`:

```php
it('resolves a diamond fan-in exactly once: B and C both complete from the same stale context, D dispatches exactly once with A+B+C all present', function (): void {
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $stepA = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $stepB = new Step('B', null, null, null, null, [], null, [], [], [], [], ['A']);
    $stepC = new Step('C', null, null, null, null, [], null, [], [], [], [], ['A']);
    $stepD = new Step('D', null, null, null, null, [], null, [], [], [], [], ['B', 'C']);
    $workflow = new Workflow('wf_1', null, null, null, [], [$stepA, $stepB, $stepC, $stepD], [], [], [], []);
    $document = makeWorkerDocument($workflow);
    $definitionId = $definitionRegistry->register($document);

    [$worker, , $store, , , $queue] = makeWorker(StepExecutionOutcome::resolved(200, [], []), $definitionRegistry);

    // A already completed and persisted by an earlier job.
    $store->preloaded['exec_1'] = [
        'definitionId' => $definitionId,
        'workflowId' => 'wf_1',
        'steps' => ['A' => ['statusCode' => 200, 'status' => 'succeeded']],
        'inputs' => [],
        'components' => [],
    ];

    // B and C were both dispatched right after A completed, so both jobs carry the exact
    // same A-only context snapshot -- this is the classic diamond/fan-in lost-update race.
    $staleContext = (new WorkflowContext($definitionId))->withExecutionId('exec_1')->withWorkflowId('wf_1');

    $worker->handle(new ExecuteStepJob($stepB, $staleContext));

    // WorkerMockStateStore keeps save()/load() as two separate arrays for test clarity
    // elsewhere in this file -- bridge them here to simulate B's write becoming visible to
    // C's subsequent load(), exactly like a real shared StateStore would.
    $store->preloaded['exec_1'] = $store->saves['exec_1'];

    $worker->handle(new ExecuteStepJob($stepC, $staleContext));

    $dDispatches = array_values(array_filter($queue->dispatched, fn ($d) => $d['job']->step->stepId === 'D'));
    expect($dDispatches)->toHaveCount(1);
    expect($store->saves['exec_1']['steps'])->toHaveKeys(['A', 'B', 'C']);
});
```

- [ ] **Step 2: Run the test**

Run: `vendor/bin/pest tests/Execution/StepExecutionWorkerTest.php --filter="diamond fan-in exactly once"`
Expected: PASS. If it fails, the most likely cause is the lock key or reload-before-evaluate logic in Task 13's `StepExecutionWorker::handle()` — re-check that `reconcileWithPersistedState()` runs before the idempotency check and before the executor call.

- [ ] **Step 3: Commit**

```bash
git add tests/Execution/StepExecutionWorkerTest.php
git commit -m "test: add full two-call diamond fan-in regression test"
```

---

## Task 20: Full suite + static analysis + code style

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `vendor/bin/pest`
Expected: PASS, no regressions anywhere in the suite. Before trusting a clean run, sanity-check nothing was silently skipped: `grep -rl "new StepExecutionWorker(\|new WorkflowContext(\|new StepOutcomeHandler(\|new Step(" tests/ | wc -l` and spot-check a few for the relocated/renamed paths from Tasks 1-3.

- [ ] **Step 2: Run static analysis**

Run: `vendor/bin/phpstan analyse`
Expected: no new errors introduced by this plan's files (level 8, per `phpstan.neon`'s existing config — `paths: [src, config]`, so only production code is analysed, not `tests/`).

- [ ] **Step 3: Run code style**

Run: `vendor/bin/pint --test`
Expected: no formatting violations. If there are, run `vendor/bin/pint` (without `--test`) to fix, then re-run Step 1 to confirm nothing broke.

- [ ] **Step 4: Commit if Pint made changes**

```bash
git add -A
git commit -m "style: pint formatting"
```

- [ ] **Step 5: Confirm no stray `tests/Unit`/`tests/Feature` files remain from files this plan touched**

Run: `find tests/Unit tests/Feature -type f 2>/dev/null` and cross-check against every file this plan relocated (Tasks 1, 2, 3, 4, 13) — none of those specific files should still exist under the old paths. Files under `tests/Unit`/`tests/Feature` that this plan never touched (anything doc 02's plan created that this plan didn't modify) are out of scope to move — leave them alone.

