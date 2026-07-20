# CQRS & Event-Sourced Persistence Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `RedisHotStateStore`/`DatabaseEventLedger`/`InMemoryDefinitionRegistry` functionally real — bound, migrated, and actually called at the right points in `StepExecutionWorker` — instead of bindable-but-uncalled scaffolding.

**Architecture:** Three IDs (`definitionId`/`workflowId`/`executionId`) replace the single overloaded `WorkflowContext::$definitionId`. The definition registry persists `ArazzoDocument::$rawRoot` verbatim (already populated by `Parser::parse()`) and reconstructs via the same `Parser::parse()` on read — no separate DTO hydrator. `StepExecutionWorker::handle()` is rewritten to fetch the document before compiling the request (so `compileRequest`/`extractOutputs` finally receive a real, non-null `$document`), save state with a TTL, record execution start, and append a step-executed event.

**Tech Stack:** PHP 8.4, Laravel 11/12/13 (via `illuminate/contracts` + host app's full framework), `illuminate/support` (`Str::ulid()`, already an implicit runtime dependency of every `src/Laravel/*.php` class in this codebase — e.g. `DatabaseEventLedger`'s `now()` helper), Orchestra Testbench + Pest for tests, sqlite for CI, Postgres-conditional raw SQL for partitioning.

## Global Constraints

- Framework-agnostic classes in `src/Execution/` (not `src/Laravel/`) must not import `Illuminate\Support\*` — that boundary is why `StepExecutionWorker` throws `LogicException` on a missing `executionId` instead of minting one with `Str::ulid()` itself. Minting the *first* `executionId` for a run is out of scope for this plan (no "start a workflow" entrypoint exists yet anywhere in the codebase — that's [03 — Native Async Control Flow](../roadmap/03-native-async-control-flow.md)'s job). Tests supply a literal `executionId` string directly.
- `arazzo_events` has no foreign key to `arazzo_executions` (index only) — the Postgres partitioning path requires a composite `(id, created_at)` primary key, which can't cleanly carry a single-column FK, and consistency between the sqlite/mysql path and the pgsql path matters more than FK enforcement on what is an audit log.
- Every file this plan touches must pass `vendor/bin/pest` and `vendor/bin/phpstan analyse` before being considered done.
- New interface beyond the three named in the design spec: `ExecutionRegistryInterface` (`src/Execution/Contracts/ExecutionRegistryInterface.php`). The spec's `arazzo_executions` table had no writer — nothing in the approved data flow ever inserted into it. Adding one small interface (one method: `start()`) closes that gap rather than shipping a table nothing writes to, which is the exact "unwired scaffolding" anti-pattern this whole plan exists to fix. Flagged here since it wasn't in the original spec's interface list.

---

## Task 1: `WorkflowContext` — add `workflowId`/`executionId`

**Files:**
- Modify: `src/Execution/WorkflowContext.php`
- Test: `tests/Unit/Execution/WorkflowContextTest.php`

**Interfaces:**
- Produces: `WorkflowContext::getWorkflowId(): ?string`, `getExecutionId(): ?string`, `withWorkflowId(string): self`, `withExecutionId(string): self`. Both new constructor params are optional and trail the existing ones — every existing `new WorkflowContext($definitionId, ...)` call site across the codebase keeps compiling unchanged. Consumed by Task 3 (`Engine`) and Task 13 (`StepExecutionWorker`).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Unit/Execution/WorkflowContextTest.php` (keep existing tests):

```php
    public function test_workflow_id_and_execution_id_default_to_null(): void
    {
        $context = new WorkflowContext('def_1');

        $this->assertNull($context->getWorkflowId());
        $this->assertNull($context->getExecutionId());
    }

    public function test_with_workflow_id_is_immutable(): void
    {
        $context = new WorkflowContext('def_1');
        $newContext = $context->withWorkflowId('wf_1');

        $this->assertNotSame($context, $newContext);
        $this->assertNull($context->getWorkflowId());
        $this->assertSame('wf_1', $newContext->getWorkflowId());
    }

    public function test_with_execution_id_is_immutable(): void
    {
        $context = new WorkflowContext('def_1');
        $newContext = $context->withExecutionId('exec_1');

        $this->assertNotSame($context, $newContext);
        $this->assertNull($context->getExecutionId());
        $this->assertSame('exec_1', $newContext->getExecutionId());
    }

    public function test_workflow_id_and_execution_id_survive_step_mutators(): void
    {
        $context = (new WorkflowContext('def_1'))
            ->withWorkflowId('wf_1')
            ->withExecutionId('exec_1')
            ->withStepRequest('step_1', ['method' => 'GET'])
            ->withStepResponse('step_1', ['statusCode' => 200])
            ->withStepOutput('step_1', 'id', 1)
            ->withStepResult('step_2', ['done' => true]);

        $this->assertSame('wf_1', $context->getWorkflowId());
        $this->assertSame('exec_1', $context->getExecutionId());
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Execution/WorkflowContextTest.php`
Expected: FAIL with "Call to undefined method ...WorkflowContext::getWorkflowId()"

- [ ] **Step 3: Implement the new fields**

Replace the full contents of `src/Execution/WorkflowContext.php`:

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
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Execution/WorkflowContextTest.php`
Expected: PASS (all tests, including the 4 new ones)

- [ ] **Step 5: Commit**

```bash
git add src/Execution/WorkflowContext.php tests/Unit/Execution/WorkflowContextTest.php
git commit -m "feat: add workflowId/executionId to WorkflowContext"
```

---

## Task 2: `StateStoreInterface` / `RedisHotStateStore` — rename param, add TTL

**Files:**
- Modify: `src/Execution/Contracts/StateStoreInterface.php`
- Modify: `src/Laravel/RedisHotStateStore.php`
- Modify: `tests/Unit/Laravel/RedisHotStateStoreTest.php`

**Interfaces:**
- Produces: `StateStoreInterface::save(string $executionId, array $state, ?int $ttlSeconds = null): void` (renamed 1st param, new 3rd param). `RedisHotStateStore` gains a constructor `$defaultTtlSeconds` param, uses `setex` instead of `set` when a TTL applies. Consumed by Task 3 (`Engine`'s test double), Task 13 (`StepExecutionWorker`), and Task 12 (service provider wiring).

- [ ] **Step 1: Write the failing tests**

Replace the full contents of `tests/Unit/Laravel/RedisHotStateStoreTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Laravel;

use Alama\LaravelArazzo\Laravel\RedisHotStateStore;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use PHPUnit\Framework\TestCase;

class RedisHotStateStoreTest extends TestCase
{
    private function makeConnection(): Connection
    {
        return new class() extends Connection
        {
            /** @var list<array<string, mixed>> */
            public array $calls = [];

            public function __construct()
            {
            }

            public function set($key, $value)
            {
                $this->calls[] = ['method' => 'set', 'key' => $key, 'value' => $value];
            }

            public function setex($key, $seconds, $value)
            {
                $this->calls[] = ['method' => 'setex', 'key' => $key, 'seconds' => $seconds, 'value' => $value];
            }

            public function get($key)
            {
                $this->calls[] = ['method' => 'get', 'key' => $key];

                return json_encode(['foo' => 'bar']);
            }

            public function createSubscription($channels, \Closure $callback, $method = 'subscribe')
            {
            }
        };
    }

    public function test_saves_with_default_ttl_and_loads_state(): void
    {
        $redisConnection = $this->makeConnection();
        $factory = $this->createMock(RedisFactory::class);
        $factory->method('connection')->willReturn($redisConnection);

        $store = new RedisHotStateStore($factory, defaultTtlSeconds: 3600);
        $store->save('exec_123', ['foo' => 'bar']);
        $result = $store->load('exec_123');

        $this->assertEquals(['foo' => 'bar'], $result);
        $this->assertEquals('setex', $redisConnection->calls[0]['method']);
        $this->assertEquals('arazzo:state:exec_123', $redisConnection->calls[0]['key']);
        $this->assertEquals(3600, $redisConnection->calls[0]['seconds']);
        $this->assertEquals(json_encode(['foo' => 'bar']), $redisConnection->calls[0]['value']);
    }

    public function test_explicit_ttl_overrides_the_default(): void
    {
        $redisConnection = $this->makeConnection();
        $factory = $this->createMock(RedisFactory::class);
        $factory->method('connection')->willReturn($redisConnection);

        $store = new RedisHotStateStore($factory, defaultTtlSeconds: 3600);
        $store->save('exec_123', ['foo' => 'bar'], ttlSeconds: 60);

        $this->assertEquals(60, $redisConnection->calls[0]['seconds']);
    }

    public function test_returns_null_when_key_is_missing(): void
    {
        $redisConnection = new class() extends Connection
        {
            public function __construct()
            {
            }

            public function get($key)
            {
                return null;
            }

            public function createSubscription($channels, \Closure $callback, $method = 'subscribe')
            {
            }
        };
        $factory = $this->createMock(RedisFactory::class);
        $factory->method('connection')->willReturn($redisConnection);

        $store = new RedisHotStateStore($factory);

        $this->assertNull($store->load('missing'));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Laravel/RedisHotStateStoreTest.php`
Expected: FAIL — `RedisHotStateStore::save()` doesn't accept a `ttlSeconds` named arg, `defaultTtlSeconds` constructor param doesn't exist, `load()` returns `[]` not `null` on miss.

- [ ] **Step 3: Update the interface**

Replace the full contents of `src/Execution/Contracts/StateStoreInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Contracts;

interface StateStoreInterface
{
    /**
     * @param array<string, mixed> $state
     */
    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void;

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $executionId): ?array;
}
```

- [ ] **Step 4: Update `RedisHotStateStore`**

Replace the full contents of `src/Laravel/RedisHotStateStore.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class RedisHotStateStore implements StateStoreInterface
{
    public function __construct(
        private RedisFactory $redis,
        private string $prefix = 'arazzo:state:',
        private int $defaultTtlSeconds = 86400,
    ) {
    }

    /**
     * @param array<string, mixed> $state
     */
    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
        $ttl = $ttlSeconds ?? $this->defaultTtlSeconds;

        $this->redis->connection()->setex($this->prefix . $executionId, $ttl, json_encode($state));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $executionId): ?array
    {
        $data = $this->redis->connection()->get($this->prefix . $executionId);

        return $data ? json_decode($data, true) : null;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Laravel/RedisHotStateStoreTest.php`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add src/Execution/Contracts/StateStoreInterface.php src/Laravel/RedisHotStateStore.php tests/Unit/Laravel/RedisHotStateStoreTest.php
git commit -m "feat: add TTL support to StateStoreInterface/RedisHotStateStore"
```

---

## Task 3: `Engine` — stamp `workflowId` onto context before dispatch

**Files:**
- Modify: `src/Execution/Engine.php`
- Modify: `tests/Unit/Execution/EngineTest.php`

**Interfaces:**
- Consumes: `WorkflowContext::getWorkflowId()`/`withWorkflowId()` (Task 1), `StateStoreInterface`'s 3-arg `save()` (Task 2 — the file's existing `MockStateStore` test double implements `StateStoreInterface` and must be updated to match, or it won't compile once Task 2 lands).
- Produces: every `ExecuteStepJob` dispatched by `Engine::evaluate()` now carries a context with `workflowId` set — this is what lets `StepExecutionWorker` (Task 13) know which `Workflow` inside a multi-workflow `ArazzoDocument` it's executing.

**Note:** `tests/Unit/Execution/EngineTest.php` already exists with one test (`test_engine_dispatches_runnable_steps`) and two test doubles (`MockQueueDriver`, `MockStateStore`). This task adds two new tests and fixes `MockStateStore` to match Task 2's new `StateStoreInterface` signature — it does not create a new file or rename the existing classes.

- [ ] **Step 1: Write the failing tests**

Replace the full contents of `tests/Unit/Execution/EngineTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use PHPUnit\Framework\TestCase;

class MockQueueDriver implements QueueDriverInterface
{
    public array $dispatched = [];

    public function dispatch(object $job, int $delaySeconds = 0): void
    {
        $this->dispatched[] = $job;
    }
}

class MockStateStore implements StateStoreInterface
{
    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
    }

    public function load(string $executionId): ?array
    {
        return null;
    }
}

class EngineTest extends TestCase
{
    public function test_engine_dispatches_runnable_steps(): void
    {
        $queue = new MockQueueDriver();
        $store = new MockStateStore();
        $analyzer = new DependencyAnalyzer();
        $engine = new Engine($analyzer, $queue, $store);

        $stepA = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
        $stepB = new Step('B', null, null, null, null, [], null, [], [], [], [], []);
        $workflow = new Workflow('w_1', null, null, [], [], [$stepA, $stepB], [], [], [], []);

        $context = new WorkflowContext('def_1');

        $engine->evaluate($workflow, $context);

        $this->assertCount(2, $queue->dispatched);
    }

    public function test_stamps_workflow_id_onto_dispatched_job_context(): void
    {
        $queue = new MockQueueDriver();
        $engine = new Engine(new DependencyAnalyzer(), $queue, new MockStateStore());

        $step = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
        $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
        $context = new WorkflowContext('def_1');

        $engine->evaluate($workflow, $context);

        $this->assertCount(1, $queue->dispatched);
        /** @var ExecuteStepJob $job */
        $job = $queue->dispatched[0];
        $this->assertSame('wf_1', $job->context->getWorkflowId());
    }

    public function test_does_not_overwrite_an_already_set_workflow_id(): void
    {
        $queue = new MockQueueDriver();
        $engine = new Engine(new DependencyAnalyzer(), $queue, new MockStateStore());

        $step = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
        $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
        $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_original');

        $engine->evaluate($workflow, $context);

        /** @var ExecuteStepJob $job */
        $job = $queue->dispatched[0];
        $this->assertSame('wf_original', $job->context->getWorkflowId());
    }
}
```

- [ ] **Step 2: Run tests to verify the new ones fail**

Run: `vendor/bin/pest tests/Unit/Execution/EngineTest.php`
Expected: the original `test_engine_dispatches_runnable_steps` still PASSes (nothing about its behavior changed); the 2 new tests FAIL — `$job->context->getWorkflowId()` returns `null`, not `'wf_1'`/`'wf_original'`.

- [ ] **Step 3: Implement the stamp**

In `src/Execution/Engine.php`, replace the `evaluate` method body:

```php
    public function evaluate(Workflow $workflow, WorkflowContext $context): void
    {
        if ($context->getWorkflowId() === null) {
            $context = $context->withWorkflowId($workflow->workflowId);
        }

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
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Execution/EngineTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Execution/Engine.php tests/Unit/Execution/EngineTest.php
git commit -m "feat: stamp workflowId onto WorkflowContext in Engine::evaluate"
```

---

## Task 4: `EventLedgerInterface` / `DatabaseEventLedger` — rename param, non-fatal errors

**Files:**
- Modify: `src/Execution/Contracts/EventLedgerInterface.php`
- Modify: `src/Laravel/DatabaseEventLedger.php`
- Modify: `tests/Unit/Laravel/DatabaseEventLedgerTest.php`

**Interfaces:**
- Produces: `EventLedgerInterface::append(string $executionId, string $eventType, array $payload): void` (renamed 1st param — semantic only, doesn't change the call signature's arity, but documents what it's always meant to identify). `DatabaseEventLedger` now catches DB write failures and logs instead of throwing. Consumed by Task 13.

- [ ] **Step 1: Write the failing tests**

Replace the full contents of `tests/Unit/Laravel/DatabaseEventLedgerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Laravel;

use Alama\LaravelArazzo\Laravel\DatabaseEventLedger;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DatabaseEventLedgerTest extends TestCase
{
    public function test_appends_event_to_database(): void
    {
        $builder = $this->createMock(Builder::class);
        $builder->expects($this->once())->method('insert')->willReturn(true);

        $db = $this->createMock(ConnectionInterface::class);
        $db->method('table')->with('arazzo_events')->willReturn($builder);

        $ledger = new DatabaseEventLedger($db, 'arazzo_events');
        $ledger->append('exec_1', 'StepExecuted', ['stepId' => 'A']);
    }

    public function test_swallows_and_logs_a_database_failure_instead_of_throwing(): void
    {
        $builder = $this->createMock(Builder::class);
        $builder->method('insert')->willThrowException(new \RuntimeException('connection refused'));

        $db = $this->createMock(ConnectionInterface::class);
        $db->method('table')->willReturn($builder);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $ledger = new DatabaseEventLedger($db, 'arazzo_events', $logger);

        // Must not throw.
        $ledger->append('exec_1', 'StepExecuted', ['stepId' => 'A']);
        $this->addToAssertionCount(1);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Laravel/DatabaseEventLedgerTest.php`
Expected: FAIL — `DatabaseEventLedger` constructor doesn't accept a 3rd `$logger` param; the failure test throws instead of being swallowed.

- [ ] **Step 3: Update the interface**

Replace the full contents of `src/Execution/Contracts/EventLedgerInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Contracts;

interface EventLedgerInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function append(string $executionId, string $eventType, array $payload): void;
}
```

- [ ] **Step 4: Update `DatabaseEventLedger`**

Replace the full contents of `src/Laravel/DatabaseEventLedger.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class DatabaseEventLedger implements EventLedgerInterface
{
    public function __construct(
        private ConnectionInterface $db,
        private string $tableName = 'arazzo_events',
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function append(string $executionId, string $eventType, array $payload): void
    {
        try {
            $this->db->table($this->tableName)->insert([
                'execution_id' => $executionId,
                'event_type' => $eventType,
                'payload' => json_encode($payload),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            $this->logger?->warning("Failed to append event '{$eventType}' for execution '{$executionId}': {$e->getMessage()}");
        }
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Laravel/DatabaseEventLedgerTest.php`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add src/Execution/Contracts/EventLedgerInterface.php src/Laravel/DatabaseEventLedger.php tests/Unit/Laravel/DatabaseEventLedgerTest.php
git commit -m "feat: rename EventLedger param to executionId, swallow DB failures"
```

---

## Task 5: `DefinitionHydrationException`

**Files:**
- Create: `src/Execution/Exceptions/DefinitionHydrationException.php`

**Interfaces:**
- Produces: `DefinitionHydrationException extends RuntimeException` — thrown by Task 8's `DatabaseDefinitionRegistry::get()`. No custom constructor needed; `RuntimeException`'s own `(string $message, int $code = 0, ?Throwable $previous = null)` constructor is used directly via named args (`new DefinitionHydrationException($msg, previous: $e)`).

This is a pure scaffolding task with no independent behavior — correctness is verified by Task 8's tests. No separate test file.

- [ ] **Step 1: Create the exception**

Create `src/Execution/Exceptions/DefinitionHydrationException.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Exceptions;

use RuntimeException;

final class DefinitionHydrationException extends RuntimeException
{
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Execution/Exceptions/DefinitionHydrationException.php
git commit -m "feat: add DefinitionHydrationException"
```

---

## Task 6: `DefinitionRegistryInterface` — `Workflow` → `ArazzoDocument`

**Files:**
- Modify: `src/Execution/Contracts/DefinitionRegistryInterface.php`

**Interfaces:**
- Produces: `DefinitionRegistryInterface::register(ArazzoDocument $document): string`, `get(string $definitionId): ?ArazzoDocument`. Every implementer (Task 7's `InMemoryDefinitionRegistry`, Task 9's `DatabaseDefinitionRegistry`) and every caller (Task 13's `StepExecutionWorker`) must be updated to match — this task alone will not compile in isolation; that's expected and resolved by Tasks 7, 9, and 13.

No independent test — a pure signature change, verified by Tasks 7, 9, and 13 compiling and passing.

- [ ] **Step 1: Update the interface**

Replace the full contents of `src/Execution/Contracts/DefinitionRegistryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Contracts;

use Alama\LaravelArazzo\Dto\ArazzoDocument;

interface DefinitionRegistryInterface
{
    public function register(ArazzoDocument $document): string;

    public function get(string $definitionId): ?ArazzoDocument;
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Execution/Contracts/DefinitionRegistryInterface.php
git commit -m "feat: widen DefinitionRegistryInterface to register/return ArazzoDocument"
```

---

## Task 7: `InMemoryDefinitionRegistry` — match the new interface

**Files:**
- Modify: `src/Execution/InMemoryDefinitionRegistry.php`
- Test: `tests/Unit/Execution/InMemoryDefinitionRegistryTest.php` (create if it doesn't exist)

**Interfaces:**
- Consumes: `DefinitionRegistryInterface` (Task 6).
- Produces: a fast, process-local test double — kept (not deleted) for tests that don't need real DB persistence, per the design spec's explicit decision to keep it as a test double alongside the new `DatabaseDefinitionRegistry`.

- [ ] **Step 1: Check whether the test file exists**

```bash
find tests -iname "InMemoryDefinitionRegistryTest.php"
```

If it exists, add to it. If not, create it as shown in Step 2.

- [ ] **Step 2: Write the failing test**

Create (or add to) `tests/Unit/Execution/InMemoryDefinitionRegistryTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Execution\InMemoryDefinitionRegistry;
use PHPUnit\Framework\TestCase;

class InMemoryDefinitionRegistryTest extends TestCase
{
    private function makeDocument(): ArazzoDocument
    {
        return new ArazzoDocument(
            arazzo: '1.0.0',
            info: new Info('Test', null, null, '1.0.0'),
            sourceDescriptions: [],
            workflows: [],
            components: new Components([], [], [], []),
            specificationExtensions: [],
        );
    }

    public function test_registers_and_retrieves_a_document(): void
    {
        $registry = new InMemoryDefinitionRegistry();
        $document = $this->makeDocument();

        $id = $registry->register($document);

        $this->assertSame($document, $registry->get($id));
    }

    public function test_returns_null_for_an_unknown_id(): void
    {
        $registry = new InMemoryDefinitionRegistry();

        $this->assertNull($registry->get('unknown'));
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Execution/InMemoryDefinitionRegistryTest.php`
Expected: FAIL — `register()` still type-hints `Workflow`, not `ArazzoDocument`.

- [ ] **Step 4: Implement the change**

Replace the full contents of `src/Execution/InMemoryDefinitionRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;

class InMemoryDefinitionRegistry implements DefinitionRegistryInterface
{
    /** @var array<string, ArazzoDocument> */
    private array $registry = [];

    public function register(ArazzoDocument $document): string
    {
        $id = 'in_memory_' . spl_object_id($document);
        $this->registry[$id] = $document;

        return $id;
    }

    public function get(string $definitionId): ?ArazzoDocument
    {
        return $this->registry[$definitionId] ?? null;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Execution/InMemoryDefinitionRegistryTest.php`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add src/Execution/InMemoryDefinitionRegistry.php tests/Unit/Execution/InMemoryDefinitionRegistryTest.php
git commit -m "feat: update InMemoryDefinitionRegistry to register/return ArazzoDocument"
```

---

## Task 8: Migrations — `arazzo_definitions`, `arazzo_executions`, `arazzo_events`

**Files:**
- Create: `database/migrations/create_arazzo_definitions_table.php`
- Create: `database/migrations/create_arazzo_executions_table.php`
- Create: `database/migrations/create_arazzo_events_table.php`
- Test: `tests/Feature/PersistenceMigrationsTest.php`

**Interfaces:**
- Produces: three tables. Consumed by Task 9 (`DatabaseDefinitionRegistry`), Task 10 (`DatabaseExecutionRegistry`), Task 4's `DatabaseEventLedger`.

**Note:** these files live in `database/migrations/` **without** the `.php.stub` extension the existing `create_skeleton_table.php.stub` uses — spatie/laravel-package-tools' `hasMigrations()` (wired in Task 12) looks for `{name}.php` first and only falls back to `.php.stub`. Using plain `.php` means Orchestra Testbench's package-migration auto-discovery (via `runsMigrations()`) picks these up directly without needing a publish step, which is what makes them testable in this plan without a real host Laravel app.

**Style note:** `tests/Pest.php` already binds `uses(TestCase::class)->in('Feature', 'Commands', 'Resolution')` — every existing file under `tests/Resolution/` (e.g. `DefaultSourceResolverTest.php`) is plain Pest `it(...)` with no `uses()` call needed in the file itself, since the directory-level binding covers it. `tests/Feature/` doesn't exist yet in this repo — this task creates it — so write this test in that same Pest style to match the convention `tests/Pest.php` already declares for that directory, not the PHPUnit-class style used under `tests/Unit/`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PersistenceMigrationsTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the arazzo_definitions table with expected columns', function (): void {
    expect(Schema::hasTable('arazzo_definitions'))->toBeTrue();
    expect(Schema::hasColumns('arazzo_definitions', [
        'id', 'document_identity', 'content_hash', 'raw_document', 'created_at',
    ]))->toBeTrue();
});

it('creates the arazzo_executions table with expected columns', function (): void {
    expect(Schema::hasTable('arazzo_executions'))->toBeTrue();
    expect(Schema::hasColumns('arazzo_executions', [
        'id', 'definition_id', 'workflow_id', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('creates the arazzo_events table with expected columns', function (): void {
    expect(Schema::hasTable('arazzo_events'))->toBeTrue();
    expect(Schema::hasColumns('arazzo_events', [
        'id', 'execution_id', 'event_type', 'payload', 'created_at',
    ]))->toBeTrue();
});

it('enforces the unique index on definitions and rejects duplicate content', function (): void {
    DB::table('arazzo_definitions')->insert([
        'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        'document_identity' => 'Test Doc',
        'content_hash' => str_repeat('a', 64),
        'raw_document' => json_encode(['x' => 1]),
        'created_at' => now(),
    ]);

    expect(fn () => DB::table('arazzo_definitions')->insert([
        'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
        'document_identity' => 'Test Doc',
        'content_hash' => str_repeat('a', 64),
        'raw_document' => json_encode(['x' => 2]),
        'created_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/PersistenceMigrationsTest.php`
Expected: FAIL — none of the three tables exist yet. (This test also won't run migrations at all until Task 12 wires `hasMigrations()`/`runsMigrations()` into the service provider — if it errors with "no such table" for all four tests, that's expected until Task 12 lands too. Come back and re-run this test after Task 12.)

- [ ] **Step 3: Create the `arazzo_definitions` migration**

Create `database/migrations/create_arazzo_definitions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arazzo_definitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('document_identity');
            $table->string('content_hash', 64);
            $table->json('raw_document');
            $table->timestamp('created_at')->nullable();

            $table->unique(['document_identity', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arazzo_definitions');
    }
};
```

- [ ] **Step 4: Create the `arazzo_executions` migration**

Create `database/migrations/create_arazzo_executions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arazzo_executions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('definition_id')->index();
            $table->foreign('definition_id')->references('id')->on('arazzo_definitions')->cascadeOnDelete();
            $table->string('workflow_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arazzo_executions');
    }
};
```

- [ ] **Step 5: Create the `arazzo_events` migration (portable base + Postgres partitioning)**

Create `database/migrations/create_arazzo_events_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->createPartitionedTable();

            return;
        }

        Schema::create('arazzo_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('execution_id')->index();
            $table->string('event_type');
            $table->json('payload');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arazzo_events');
    }

    private function createPartitionedTable(): void
    {
        // Postgres native RANGE partitioning requires the partition key (created_at) to be
        // part of the primary key, so this can't reuse the portable Schema::create() shape
        // above -- and a partitioned table can't carry a single-column FK, matching the
        // no-FK decision on the portable path (see plan's Global Constraints).
        DB::statement(<<<'SQL'
            CREATE TABLE arazzo_events (
                id BIGSERIAL,
                execution_id CHAR(26) NOT NULL,
                event_type VARCHAR(255) NOT NULL,
                payload JSONB NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT now(),
                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at)
        SQL);

        DB::statement('CREATE INDEX arazzo_events_execution_id_index ON arazzo_events (execution_id)');
        DB::statement('CREATE TABLE arazzo_events_default PARTITION OF arazzo_events DEFAULT');
    }
};
```

- [ ] **Step 6: Run test to verify it still fails the same way (migrations exist but aren't loaded yet)**

Run: `vendor/bin/pest tests/Feature/PersistenceMigrationsTest.php`
Expected: still FAIL — the migration files exist on disk now, but nothing loads them into Testbench until Task 12 wires `hasMigrations()`. This is expected; don't debug further here.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/create_arazzo_definitions_table.php database/migrations/create_arazzo_executions_table.php database/migrations/create_arazzo_events_table.php tests/Feature/PersistenceMigrationsTest.php
git commit -m "feat: add migrations for arazzo_definitions/executions/events"
```

**Do not mark this task's checkboxes fully verified until Task 12 lands and Step 6's test is re-run to confirm PASS.** Add a note in your own tracking to revisit `tests/Feature/PersistenceMigrationsTest.php` after Task 12.

---

## Task 9: `DatabaseDefinitionRegistry`

**Files:**
- Create: `src/Laravel/DatabaseDefinitionRegistry.php`
- Test: `tests/Unit/Laravel/DatabaseDefinitionRegistryTest.php`

**Interfaces:**
- Consumes: `DefinitionRegistryInterface` (Task 6), `arazzo_definitions` table (Task 8), `Parser::parse()` (existing, unchanged), `DefinitionHydrationException` (Task 5).
- Produces: the real, persistent registry. Consumed by Task 12 (wiring) and Task 13 (`StepExecutionWorker`).

This test needs a real database, not mocks — extend the package's Testbench-backed `TestCase`, not plain PHPUnit `TestCase`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Laravel/DatabaseDefinitionRegistryTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Laravel;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Execution\Exceptions\DefinitionHydrationException;
use Alama\LaravelArazzo\Laravel\DatabaseDefinitionRegistry;
use Alama\LaravelArazzo\Parser\Parser;
use Alama\LaravelArazzo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class DatabaseDefinitionRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function rawRoot(string $title = 'Test Doc'): array
    {
        return [
            'arazzo' => '1.0.0',
            'info' => ['title' => $title, 'version' => '1.0'],
            'sourceDescriptions' => [],
            'workflows' => [
                [
                    'workflowId' => 'wf_1',
                    'steps' => [
                        ['stepId' => 'step_1', 'operationId' => 'op_1'],
                    ],
                ],
            ],
        ];
    }

    private function documentFor(array $rawRoot): ArazzoDocument
    {
        return (new Parser())->parse(new \Alama\LaravelArazzo\Dto\RawDocument(
            $rawRoot,
            'memory://test',
            \Alama\LaravelArazzo\Dto\Enum\Format::Json,
        ));
    }

    public function test_registers_and_retrieves_a_document(): void
    {
        $registry = new DatabaseDefinitionRegistry(DB::connection(), new Parser());
        $document = $this->documentFor($this->rawRoot());

        $id = $registry->register($document);
        $fetched = $registry->get($id);

        $this->assertNotNull($fetched);
        $this->assertSame('Test Doc', $fetched->info->title);
        $this->assertSame('wf_1', $fetched->workflows[0]->workflowId);
    }

    public function test_registering_identical_content_twice_returns_the_same_id(): void
    {
        $registry = new DatabaseDefinitionRegistry(DB::connection(), new Parser());
        $document = $this->documentFor($this->rawRoot());

        $id1 = $registry->register($document);
        $id2 = $registry->register($document);

        $this->assertSame($id1, $id2);
        $this->assertSame(1, DB::table('arazzo_definitions')->count());
    }

    public function test_registering_different_content_produces_different_ids(): void
    {
        $registry = new DatabaseDefinitionRegistry(DB::connection(), new Parser());

        $id1 = $registry->register($this->documentFor($this->rawRoot('Doc A')));
        $id2 = $registry->register($this->documentFor($this->rawRoot('Doc B')));

        $this->assertNotSame($id1, $id2);
    }

    public function test_get_returns_null_for_unknown_id(): void
    {
        $registry = new DatabaseDefinitionRegistry(DB::connection(), new Parser());

        $this->assertNull($registry->get('01ARZ3NDEKTSV4RRFFQ69G5FAV'));
    }

    public function test_get_throws_hydration_exception_on_unparseable_json(): void
    {
        DB::table('arazzo_definitions')->insert([
            'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'document_identity' => 'Broken',
            'content_hash' => str_repeat('a', 64),
            'raw_document' => 'not valid json',
            'created_at' => now(),
        ]);

        $registry = new DatabaseDefinitionRegistry(DB::connection(), new Parser());

        $this->expectException(DefinitionHydrationException::class);
        $registry->get('01ARZ3NDEKTSV4RRFFQ69G5FAV');
    }

    public function test_get_throws_hydration_exception_when_content_no_longer_validates(): void
    {
        // Missing required "workflows" field -- Parser::parse() will reject this.
        DB::table('arazzo_definitions')->insert([
            'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'document_identity' => 'Invalid',
            'content_hash' => str_repeat('a', 64),
            'raw_document' => json_encode(['arazzo' => '1.0.0', 'info' => ['title' => 'x', 'version' => '1.0']]),
            'created_at' => now(),
        ]);

        $registry = new DatabaseDefinitionRegistry(DB::connection(), new Parser());

        $this->expectException(DefinitionHydrationException::class);
        $registry->get('01ARZ3NDEKTSV4RRFFQ69G5FAV');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Laravel/DatabaseDefinitionRegistryTest.php`
Expected: FAIL — `DatabaseDefinitionRegistry` doesn't exist yet. (These tests also depend on Task 12's `hasMigrations()` wiring to have a real `arazzo_definitions` table — if Task 12 hasn't landed yet, you'll see "no such table" instead. That's fine; do Task 12 before finishing this task's verification if you're executing tasks strictly in order, or come back to confirm PASS after Task 12.)

- [ ] **Step 3: Implement `DatabaseDefinitionRegistry`**

Create `src/Laravel/DatabaseDefinitionRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Enum\Format;
use Alama\LaravelArazzo\Dto\RawDocument;
use Alama\LaravelArazzo\Exceptions\ParserException;
use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\LaravelArazzo\Execution\Exceptions\DefinitionHydrationException;
use Alama\LaravelArazzo\Parser\Parser;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DatabaseDefinitionRegistry implements DefinitionRegistryInterface
{
    public function __construct(
        private ConnectionInterface $db,
        private Parser $parser,
        private string $tableName = 'arazzo_definitions',
    ) {
    }

    public function register(ArazzoDocument $document): string
    {
        $raw = $document->rawRoot;
        if ($raw === null) {
            throw new InvalidArgumentException('Cannot register an ArazzoDocument with no rawRoot.');
        }

        $identity = $document->info->title;
        $contentHash = $this->hash($raw);

        $existingId = $this->db->table($this->tableName)
            ->where('document_identity', $identity)
            ->where('content_hash', $contentHash)
            ->value('id');

        if ($existingId !== null) {
            return (string) $existingId;
        }

        $id = (string) Str::ulid();

        $this->db->table($this->tableName)->insertOrIgnore([
            'id' => $id,
            'document_identity' => $identity,
            'content_hash' => $contentHash,
            'raw_document' => json_encode($raw),
            'created_at' => now(),
        ]);

        // A concurrent register() may have won the race; re-select rather than trust $id.
        return (string) $this->db->table($this->tableName)
            ->where('document_identity', $identity)
            ->where('content_hash', $contentHash)
            ->value('id');
    }

    public function get(string $definitionId): ?ArazzoDocument
    {
        $row = $this->db->table($this->tableName)->where('id', $definitionId)->first();
        if ($row === null) {
            return null;
        }

        $decoded = json_decode((string) $row->raw_document, true);
        if (!is_array($decoded)) {
            throw new DefinitionHydrationException("Definition '{$definitionId}' has unparseable raw_document JSON.");
        }

        try {
            return $this->parser->parse(new RawDocument($decoded, "db://{$definitionId}", Format::Json));
        } catch (ParserException $e) {
            throw new DefinitionHydrationException(
                "Definition '{$definitionId}' no longer passes validation: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function hash(array $raw): string
    {
        $canonical = json_encode($this->sortRecursive($raw));

        return hash('sha256', $canonical === false ? '' : $canonical);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sortRecursive(array $data): array
    {
        ksort($data);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sortRecursive($value);
            }
        }

        return $data;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Laravel/DatabaseDefinitionRegistryTest.php`
Expected: PASS (6 tests) — once Task 12's migrations are wired.

- [ ] **Step 5: Commit**

```bash
git add src/Laravel/DatabaseDefinitionRegistry.php tests/Unit/Laravel/DatabaseDefinitionRegistryTest.php
git commit -m "feat: implement DatabaseDefinitionRegistry backed by ArazzoDocument::rawRoot"
```

---

## Task 10: `ExecutionRegistryInterface` / `DatabaseExecutionRegistry`

**Files:**
- Create: `src/Execution/Contracts/ExecutionRegistryInterface.php`
- Create: `src/Laravel/DatabaseExecutionRegistry.php`
- Test: `tests/Unit/Laravel/DatabaseExecutionRegistryTest.php`

**Interfaces:**
- Produces: `ExecutionRegistryInterface::start(string $executionId, string $definitionId, string $workflowId): void` — idempotent (safe to call once per step in a run, not just once per run). Consumed by Task 13 (`StepExecutionWorker`).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Laravel/DatabaseExecutionRegistryTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Laravel;

use Alama\LaravelArazzo\Laravel\DatabaseExecutionRegistry;
use Alama\LaravelArazzo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class DatabaseExecutionRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_inserts_an_execution_row(): void
    {
        DB::table('arazzo_definitions')->insert([
            'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'document_identity' => 'Test',
            'content_hash' => str_repeat('a', 64),
            'raw_document' => json_encode(['x' => 1]),
            'created_at' => now(),
        ]);

        $registry = new DatabaseExecutionRegistry(DB::connection());
        $registry->start('exec_1', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'wf_1');

        $this->assertDatabaseHas('arazzo_executions', [
            'id' => 'exec_1',
            'definition_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'workflow_id' => 'wf_1',
        ]);
    }

    public function test_start_is_idempotent_across_repeated_calls(): void
    {
        DB::table('arazzo_definitions')->insert([
            'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'document_identity' => 'Test',
            'content_hash' => str_repeat('a', 64),
            'raw_document' => json_encode(['x' => 1]),
            'created_at' => now(),
        ]);

        $registry = new DatabaseExecutionRegistry(DB::connection());
        $registry->start('exec_1', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'wf_1');
        $registry->start('exec_1', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'wf_1');

        $this->assertSame(1, DB::table('arazzo_executions')->where('id', 'exec_1')->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Laravel/DatabaseExecutionRegistryTest.php`
Expected: FAIL — `DatabaseExecutionRegistry` doesn't exist.

- [ ] **Step 3: Create the interface**

Create `src/Execution/Contracts/ExecutionRegistryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Contracts;

interface ExecutionRegistryInterface
{
    public function start(string $executionId, string $definitionId, string $workflowId): void;
}
```

- [ ] **Step 4: Implement `DatabaseExecutionRegistry`**

Create `src/Laravel/DatabaseExecutionRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Laravel/DatabaseExecutionRegistryTest.php`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add src/Execution/Contracts/ExecutionRegistryInterface.php src/Laravel/DatabaseExecutionRegistry.php tests/Unit/Laravel/DatabaseExecutionRegistryTest.php
git commit -m "feat: add ExecutionRegistryInterface/DatabaseExecutionRegistry"
```

---

## Task 11: `config/arazzo.php` — new keys

**Files:**
- Modify: `config/arazzo.php`

**Interfaces:**
- Produces: `config('arazzo.hot_state_ttl')`, `config('arazzo.events_table')`, `config('arazzo.definitions_table')`, `config('arazzo.executions_table')`. Consumed by Task 12.

No test — a config file has no behavior of its own; correctness is verified by Task 12's bindings test reading these values.

- [ ] **Step 1: Add the new keys**

Replace the full contents of `config/arazzo.php`:

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
];
```

- [ ] **Step 2: Commit**

```bash
git add config/arazzo.php
git commit -m "feat: add persistence config keys"
```

---

## Task 12: Wire everything in `LaravelArazzoServiceProvider`

**Files:**
- Modify: `src/LaravelArazzoServiceProvider.php`

**Interfaces:**
- Consumes: everything from Tasks 1-11.
- Produces: `StateStoreInterface`, `EventLedgerInterface`, `DefinitionRegistryInterface`, `ExecutionRegistryInterface`, and `StepExecutionWorker` all resolvable from the container; package migrations auto-discovered by Testbench. Consumed by Task 13's test and by real Laravel apps installing this package.

- [ ] **Step 1: Wire migrations in `configurePackage()`**

In `src/LaravelArazzoServiceProvider.php`, replace:

```php
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-arazzo')
            ->hasConfigFile('arazzo');
    }
```

with:

```php
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-arazzo')
            ->hasConfigFile('arazzo')
            ->hasMigrations([
                'create_arazzo_definitions_table',
                'create_arazzo_executions_table',
                'create_arazzo_events_table',
            ])
            ->runsMigrations();
    }
```

- [ ] **Step 2: Add the persistence bindings**

In `src/LaravelArazzoServiceProvider.php`, add these imports alongside the existing ones:

```php
use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\StepExecutionWorker;
use Alama\LaravelArazzo\Laravel\DatabaseDefinitionRegistry;
use Alama\LaravelArazzo\Laravel\DatabaseEventLedger;
use Alama\LaravelArazzo\Laravel\DatabaseExecutionRegistry;
use Alama\LaravelArazzo\Laravel\RedisHotStateStore;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
```

Then add this block inside `packageRegistered()`, after the existing `WorkflowExecutor` binding:

```php
        // Persistence
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
                $app->bound(\Psr\Log\LoggerInterface::class) ? $app->make(\Psr\Log\LoggerInterface::class) : null,
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

        $this->app->singleton(StepExecutionWorker::class, function ($app) {
            return new StepExecutionWorker(
                $app->make(\Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface::class),
                $app->make(StateStoreInterface::class),
                $app->make(Engine::class),
                $app->make(ClientInterface::class),
                $app->make(ExpressionResolverInterface::class),
                $app->make(DefinitionRegistryInterface::class),
                $app->make(EventLedgerInterface::class),
                $app->make(ExecutionRegistryInterface::class),
            );
        });
```

**Note:** `LockManagerInterface`, `HttpClientInterface` (via `ClientInterface`), `Engine`, and `QueueDriverInterface` are not currently bound in this provider either — they're part of the same "unwired scaffolding" this whole item is fixing, but binding `LockManagerInterface`/`Engine`/`QueueDriverInterface` themselves is outside this plan's scope (item 03's territory — queue/lock infrastructure, not persistence). If `$app->make(LockManagerInterface::class)` or `$app->make(Engine::class)` fails to resolve in Task 13's test because nothing binds them, bind them minimally in that test's setup (e.g. `$this->app->bind(LockManagerInterface::class, LaravelRedisLockManager::class)`) rather than adding them here — keep this provider's diff scoped to what this plan owns.

- [ ] **Step 3: Run the earlier tests that depend on this wiring**

Run: `vendor/bin/pest tests/Feature/PersistenceMigrationsTest.php tests/Unit/Laravel/DatabaseDefinitionRegistryTest.php tests/Unit/Laravel/DatabaseExecutionRegistryTest.php`
Expected: PASS (all tests from Tasks 8-10 now pass with real migrations loaded)

- [ ] **Step 4: Commit**

```bash
git add src/LaravelArazzoServiceProvider.php
git commit -m "feat: wire persistence interfaces and migrations into service provider"
```

---

## Task 13: `StepExecutionWorker` — the real data flow

**Files:**
- Modify: `src/Execution/StepExecutionWorker.php`
- Modify: `tests/Unit/Execution/StepExecutionWorkerTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1-10.
- Produces: the fixed call sites described in the design spec's Data Flow section.

- [ ] **Step 1: Write the failing tests**

Replace the full contents of `tests/Unit/Execution/StepExecutionWorkerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\InMemoryDefinitionRegistry;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Execution\StepExecutionWorker;
use Alama\LaravelArazzo\Execution\SyncQueueDriver;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class StepExecutionMockLockManager implements LockManagerInterface
{
    public int $acquireCount = 0;

    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $this->acquireCount++;

        return $callback();
    }
}
class StepExecutionMockStateStore implements StateStoreInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $saves = [];
    /** @var array<string, int|null> */
    public array $ttls = [];

    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
        $this->saves[$executionId] = $state;
        $this->ttls[$executionId] = $ttlSeconds;
    }

    public function load(string $executionId): ?array
    {
        return null;
    }
}
class StepExecutionMockExpressionResolver implements ExpressionResolverInterface
{
    public ?ArazzoDocument $lastDocumentSeenByCompileRequest = null;

    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
    {
        $this->lastDocumentSeenByCompileRequest = $document;

        return new Request('GET', 'http://localhost');
    }

    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        return [];
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }
}
class StepExecutionMockHttpClient implements HttpClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return new Response(200);
    }
}
class StepExecutionMockQueueDriver implements QueueDriverInterface
{
    /** @var list<array{job: object, delaySeconds: int}> */
    public array $dispatched = [];

    public function dispatch(object $job, int $delaySeconds = 0): void
    {
        $this->dispatched[] = ['job' => $job, 'delaySeconds' => $delaySeconds];
    }
}
class StepExecutionMockEventLedger implements EventLedgerInterface
{
    /** @var list<array{executionId: string, eventType: string, payload: array<string, mixed>}> */
    public array $appended = [];

    public function append(string $executionId, string $eventType, array $payload): void
    {
        $this->appended[] = ['executionId' => $executionId, 'eventType' => $eventType, 'payload' => $payload];
    }
}
class StepExecutionMockExecutionRegistry implements ExecutionRegistryInterface
{
    /** @var list<array{executionId: string, definitionId: string, workflowId: string}> */
    public array $started = [];

    public function start(string $executionId, string $definitionId, string $workflowId): void
    {
        $this->started[] = ['executionId' => $executionId, 'definitionId' => $definitionId, 'workflowId' => $workflowId];
    }
}

class StepExecutionWorkerTest extends TestCase
{
    private function makeDocument(Workflow $workflow): ArazzoDocument
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

    public function test_skips_already_completed_step(): void
    {
        $lockManager = new StepExecutionMockLockManager();
        $store = new StepExecutionMockStateStore();
        $resolver = new StepExecutionMockExpressionResolver();
        $client = new StepExecutionMockHttpClient();
        $queue = new SyncQueueDriver();
        $engine = new Engine(new DependencyAnalyzer(), $queue, $store);
        $definitionRegistry = new InMemoryDefinitionRegistry();
        $eventLedger = new StepExecutionMockEventLedger();
        $executionRegistry = new StepExecutionMockExecutionRegistry();

        $worker = new StepExecutionWorker($lockManager, $store, $engine, $client, $resolver, $definitionRegistry, $eventLedger, $executionRegistry);

        $step = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
        $context = (new WorkflowContext('def_1'))->withExecutionId('exec_1')->withStepResult('A', ['success' => true]);

        $job = new ExecuteStepJob($step, $context);
        $worker->handle($job);

        $this->assertEquals(1, $lockManager->acquireCount);
        $this->assertEmpty($store->saves);
        $this->assertEmpty($eventLedger->appended);
    }

    public function test_throws_when_context_has_no_execution_id(): void
    {
        $lockManager = new StepExecutionMockLockManager();
        $store = new StepExecutionMockStateStore();
        $resolver = new StepExecutionMockExpressionResolver();
        $client = new StepExecutionMockHttpClient();
        $queue = new SyncQueueDriver();
        $engine = new Engine(new DependencyAnalyzer(), $queue, $store);
        $definitionRegistry = new InMemoryDefinitionRegistry();
        $eventLedger = new StepExecutionMockEventLedger();
        $executionRegistry = new StepExecutionMockExecutionRegistry();

        $worker = new StepExecutionWorker($lockManager, $store, $engine, $client, $resolver, $definitionRegistry, $eventLedger, $executionRegistry);

        $step = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
        $context = new WorkflowContext('def_1'); // no executionId set

        $job = new ExecuteStepJob($step, $context);

        $this->expectException(\LogicException::class);
        $worker->handle($job);
    }

    public function test_appends_definition_missing_event_when_registry_returns_null(): void
    {
        $lockManager = new StepExecutionMockLockManager();
        $store = new StepExecutionMockStateStore();
        $resolver = new StepExecutionMockExpressionResolver();
        $client = new StepExecutionMockHttpClient();
        $queue = new SyncQueueDriver();
        $engine = new Engine(new DependencyAnalyzer(), $queue, $store);
        $definitionRegistry = new InMemoryDefinitionRegistry(); // nothing registered
        $eventLedger = new StepExecutionMockEventLedger();
        $executionRegistry = new StepExecutionMockExecutionRegistry();

        $worker = new StepExecutionWorker($lockManager, $store, $engine, $client, $resolver, $definitionRegistry, $eventLedger, $executionRegistry);

        $step = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
        $context = (new WorkflowContext('missing_def'))->withExecutionId('exec_1');

        $job = new ExecuteStepJob($step, $context);
        $worker->handle($job);

        $this->assertCount(1, $eventLedger->appended);
        $this->assertSame('execution.definition_missing', $eventLedger->appended[0]['eventType']);
        $this->assertEmpty($store->saves);
    }

    public function test_executes_step_saves_state_with_ttl_appends_event_and_starts_execution(): void
    {
        $lockManager = new StepExecutionMockLockManager();
        $store = new StepExecutionMockStateStore();
        $resolver = new StepExecutionMockExpressionResolver();
        $client = new StepExecutionMockHttpClient();
        $queue = new SyncQueueDriver();
        $engine = new Engine(new DependencyAnalyzer(), $queue, $store);
        $definitionRegistry = new InMemoryDefinitionRegistry();
        $eventLedger = new StepExecutionMockEventLedger();
        $executionRegistry = new StepExecutionMockExecutionRegistry();

        $step = new Step('B', null, null, null, null, [], null, [], [], [], [], []);
        $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
        $document = $this->makeDocument($workflow);
        $definitionId = $definitionRegistry->register($document);

        $worker = new StepExecutionWorker(
            $lockManager, $store, $engine, $client, $resolver, $definitionRegistry, $eventLedger, $executionRegistry,
            stateTtlSeconds: 3600,
        );

        $context = (new WorkflowContext($definitionId))->withExecutionId('exec_1')->withWorkflowId('wf_1');

        $job = new ExecuteStepJob($step, $context);
        $worker->handle($job);

        $this->assertArrayHasKey('exec_1', $store->saves);
        $this->assertSame(3600, $store->ttls['exec_1']);
        $this->assertArrayHasKey('B', $store->saves['exec_1']['steps']);

        $this->assertCount(1, $eventLedger->appended);
        $this->assertSame('step.executed', $eventLedger->appended[0]['eventType']);
        $this->assertSame('exec_1', $eventLedger->appended[0]['executionId']);

        $this->assertCount(1, $executionRegistry->started);
        $this->assertSame('exec_1', $executionRegistry->started[0]['executionId']);
        $this->assertSame('wf_1', $executionRegistry->started[0]['workflowId']);

        // compileRequest/extractOutputs should have received the real document, not null.
        $this->assertNotNull($resolver->lastDocumentSeenByCompileRequest);
        $this->assertSame($document, $resolver->lastDocumentSeenByCompileRequest);
    }

    public function test_dispatches_newly_unlocked_downstream_step_after_success(): void
    {
        $lockManager = new StepExecutionMockLockManager();
        $store = new StepExecutionMockStateStore();
        $resolver = new StepExecutionMockExpressionResolver();
        $client = new StepExecutionMockHttpClient();
        $queue = new SyncQueueDriver();
        $engine = new Engine(new DependencyAnalyzer(), $queue, $store);
        $definitionRegistry = new InMemoryDefinitionRegistry();
        $eventLedger = new StepExecutionMockEventLedger();
        $executionRegistry = new StepExecutionMockExecutionRegistry();

        $stepA = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
        $stepB = new Step('B', null, null, null, null, [], null, [], [], [], [], ['A']);
        $workflow = new Workflow('wf_1', null, null, null, [], [$stepA, $stepB], [], [], [], []);
        $document = $this->makeDocument($workflow);
        $definitionId = $definitionRegistry->register($document);

        $worker = new StepExecutionWorker($lockManager, $store, $engine, $client, $resolver, $definitionRegistry, $eventLedger, $executionRegistry);

        $context = (new WorkflowContext($definitionId))->withExecutionId('exec_1')->withWorkflowId('wf_1');

        $job = new ExecuteStepJob($stepA, $context);
        $worker->handle($job);

        $this->assertCount(1, $queue->dispatched);
        $dispatchedJob = $queue->dispatched[0]['job'];
        $this->assertSame('B', $dispatchedJob->step->stepId);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Execution/StepExecutionWorkerTest.php`
Expected: FAIL — `StepExecutionWorker`'s constructor doesn't accept `$eventLedger`/`$executionRegistry`/`$stateTtlSeconds`; `handle()` doesn't throw on missing `executionId`, doesn't fetch the document before `compileRequest`, doesn't append events for real.

- [ ] **Step 3: Implement the rewrite**

Replace the full contents of `src/Execution/StepExecutionWorker.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;

class StepExecutionWorker
{
    public function __construct(
        private LockManagerInterface $lockManager,
        private StateStoreInterface $stateStore,
        private Engine $engine,
        private HttpClientInterface $httpClient,
        private ExpressionResolverInterface $expressionResolver,
        private DefinitionRegistryInterface $definitionRegistry,
        private EventLedgerInterface $eventLedger,
        private ExecutionRegistryInterface $executionRegistry,
        private ?LoggerInterface $logger = null,
        private int $stateTtlSeconds = 86400,
    ) {
    }

    public function handle(ExecuteStepJob $job): void
    {
        $lockKey = "workflow_lock_{$job->context->getDefinitionId()}";

        $this->lockManager->acquire($lockKey, 30, function () use ($job) {
            $context = $job->context;
            $step = $job->step;

            if (array_key_exists($step->stepId, $context->getSteps())) {
                return;
            }

            $executionId = $context->getExecutionId();
            if ($executionId === null) {
                throw new LogicException(
                    "ExecuteStepJob for step '{$step->stepId}' has no executionId -- the workflow run was not initialized before dispatch."
                );
            }

            $document = $this->definitionRegistry->get($context->getDefinitionId());
            if ($document === null) {
                $this->eventLedger->append($executionId, 'execution.definition_missing', [
                    'definitionId' => $context->getDefinitionId(),
                ]);

                return;
            }

            $request = $this->expressionResolver->compileRequest($step, $context, $document);

            // Note: In real scenarios, we would handle RateLimitException here
            $response = $this->httpClient->sendRequest($request);

            $outputs = $this->expressionResolver->extractOutputs($step, $context, $document);

            $newContext = $context->withStepResult($step->stepId, [
                'statusCode' => $response->getStatusCode(),
                'outputs' => $outputs,
            ]);

            $this->stateStore->save($executionId, [
                'definitionId' => $newContext->getDefinitionId(),
                'workflowId' => $newContext->getWorkflowId(),
                'steps' => $newContext->getSteps(),
                'inputs' => $newContext->getInputs(),
                'components' => $newContext->getComponents(),
            ], $this->stateTtlSeconds);

            $workflowId = $newContext->getWorkflowId();
            if ($workflowId !== null) {
                $this->executionRegistry->start($executionId, $newContext->getDefinitionId(), $workflowId);
            }

            try {
                $this->eventLedger->append($executionId, 'step.executed', [
                    'stepId' => $step->stepId,
                    'statusCode' => $response->getStatusCode(),
                    'outputs' => $outputs,
                ]);
            } catch (Throwable $e) {
                $this->logger?->warning("Failed to append event ledger entry for step '{$step->stepId}': {$e->getMessage()}");
            }

            $workflow = null;
            foreach ($document->workflows as $candidate) {
                if ($candidate->workflowId === $workflowId) {
                    $workflow = $candidate;

                    break;
                }
            }

            if ($workflow !== null) {
                $this->engine->evaluate($workflow, $newContext);
            }
        });
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Execution/StepExecutionWorkerTest.php`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Execution/StepExecutionWorker.php tests/Unit/Execution/StepExecutionWorkerTest.php
git commit -m "feat: wire real persistence call sites into StepExecutionWorker"
```

---

## Task 14: Extend `LaravelArazzoServiceProviderBindingsTest`

**Files:**
- Modify: `tests/LaravelArazzoServiceProviderBindingsTest.php`

**Interfaces:**
- Consumes: Task 12's wiring.

- [ ] **Step 1: Write the failing test**

Add to `tests/LaravelArazzoServiceProviderBindingsTest.php` (add these imports at the top alongside the existing ones: `use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;`, `use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;`, `use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;`, `use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;`, `use Alama\LaravelArazzo\Execution\StepExecutionWorker;`, `use Alama\LaravelArazzo\Laravel\DatabaseDefinitionRegistry;`, `use Alama\LaravelArazzo\Laravel\DatabaseEventLedger;`, `use Alama\LaravelArazzo\Laravel\DatabaseExecutionRegistry;`, `use Alama\LaravelArazzo\Laravel\RedisHotStateStore;`):

```php
it('binds the persistence interfaces to their Laravel implementations', function () {
    expect(app(StateStoreInterface::class))->toBeInstanceOf(RedisHotStateStore::class);
    expect(app(EventLedgerInterface::class))->toBeInstanceOf(DatabaseEventLedger::class);
    expect(app(DefinitionRegistryInterface::class))->toBeInstanceOf(DatabaseDefinitionRegistry::class);
    expect(app(ExecutionRegistryInterface::class))->toBeInstanceOf(DatabaseExecutionRegistry::class);
});

it('binds StepExecutionWorker', function () {
    expect(app(StepExecutionWorker::class))->toBeInstanceOf(StepExecutionWorker::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/LaravelArazzoServiceProviderBindingsTest.php`
Expected: FAIL if any binding from Task 12 is missing or misconfigured — e.g. `app(StateStoreInterface::class)` throws if `RedisFactory` can't resolve in the test environment. If that happens, check whether the test's Testbench app has a Redis connection configured; if not, this surfaces a real gap in Task 12's wiring assumptions to fix there, not a reason to weaken this test.

- [ ] **Step 3: Confirm it passes (no implementation change expected — Task 12 already did the work)**

Run: `vendor/bin/pest tests/LaravelArazzoServiceProviderBindingsTest.php`
Expected: PASS (all bindings tests, including the 2 new ones)

- [ ] **Step 4: Commit**

```bash
git add tests/LaravelArazzoServiceProviderBindingsTest.php
git commit -m "test: assert persistence interfaces are bound in the service provider"
```

---

## Task 15: Full suite + static analysis

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `vendor/bin/pest`
Expected: PASS, no regressions in any file this plan touched or any file that depends on them (search for other `new WorkflowContext(`, `new StepExecutionWorker(`, `new DatabaseEventLedger(`, `InMemoryDefinitionRegistry` call sites across `tests/` first with `grep -rl "new WorkflowContext(\|new StepExecutionWorker(\|InMemoryDefinitionRegistry" tests/` to make sure none were missed).

- [ ] **Step 2: Run static analysis**

Run: `vendor/bin/phpstan analyse`
Expected: no new errors introduced by this plan's files.

- [ ] **Step 3: Run code style**

Run: `vendor/bin/pint --test`
Expected: no formatting violations. If there are, run `vendor/bin/pint` (without `--test`) to fix, then re-run the full suite from Step 1 to confirm nothing broke.

- [ ] **Step 4: Commit if Pint made changes**

```bash
git add -A
git commit -m "style: pint formatting"
```
