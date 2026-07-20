# Dual-Store Persistence (CQRS / Event Sourcing) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the CQRS dual-store persistence architecture. This involves a Redis-backed hot-state cache for rapid execution context read/writes, a PostgreSQL-backed append-only event ledger for auditability, and a Definition Registry for zero-downtime workflow versioning.

**Architecture:**
1. **RedisHotStateStore**: Implements `StateStoreInterface`. Uses `Illuminate\Contracts\Redis\Factory` to read/write `WorkflowContext` data as JSON in Redis.
2. **EventLedger**: Introduces `EventLedgerInterface` and `DatabaseEventLedger` (using Laravel's DB builder).
3. **DefinitionRegistry**: Introduces `DefinitionRegistryInterface` and `InMemoryDefinitionRegistry` (for caching parsed `Workflow` objects by hash/ID to enable zero-downtime versioning).

**Tech Stack:** PHP 8.2+, Pest/PHPUnit, Laravel Redis, Laravel Database.

---

### Task 1: RedisHotStateStore Implementation

**Files:**
- Create: `src/Laravel/RedisHotStateStore.php`
- Create: `tests/Unit/Laravel/RedisHotStateStoreTest.php`

- [ ] **Step 1: Write the failing test**
Create `tests/Unit/Laravel/RedisHotStateStoreTest.php` using a mock Redis factory.
```php
<?php
namespace Tests\Unit\Laravel;

use Alama\LaravelArazzo\Laravel\RedisHotStateStore;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use PHPUnit\Framework\TestCase;

class RedisHotStateStoreTest extends TestCase
{
    public function test_saves_and_loads_state(): void
    {
        $redisConnection = $this->createMock(Connection::class);
        $redisConnection->expects($this->once())
            ->method('set')
            ->with('arazzo:state:wf_123', json_encode(['foo' => 'bar']));
            
        $redisConnection->expects($this->once())
            ->method('get')
            ->with('arazzo:state:wf_123')
            ->willReturn(json_encode(['foo' => 'bar']));

        $factory = $this->createMock(RedisFactory::class);
        $factory->method('connection')->willReturn($redisConnection);

        $store = new RedisHotStateStore($factory);
        $store->save('wf_123', ['foo' => 'bar']);
        
        $this->assertEquals(['foo' => 'bar'], $store->load('wf_123'));
    }
}
```

- [ ] **Step 2: Implement RedisHotStateStore**
Create `src/Laravel/RedisHotStateStore.php` implementing `Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface`.
```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class RedisHotStateStore implements StateStoreInterface
{
    public function __construct(private RedisFactory $redis, private string $prefix = 'arazzo:state:') {}

    public function save(string $id, array $state): void
    {
        $this->redis->connection()->set($this->prefix . $id, json_encode($state));
    }

    public function load(string $id): array
    {
        $data = $this->redis->connection()->get($this->prefix . $id);
        return $data ? json_decode($data, true) : [];
    }
}
```

- [ ] **Step 3: Run test and commit**
Run `vendor/bin/phpunit tests/Unit/Laravel/RedisHotStateStoreTest.php`
Commit: `feat: implement RedisHotStateStore`

---

### Task 2: DatabaseEventLedger

**Files:**
- Create: `src/Execution/Contracts/EventLedgerInterface.php`
- Create: `src/Laravel/DatabaseEventLedger.php`
- Create: `tests/Unit/Laravel/DatabaseEventLedgerTest.php`

- [ ] **Step 1: Define Interface and write failing test**
Create `EventLedgerInterface` with `append(string $workflowId, string $eventType, array $payload): void`.
Create `DatabaseEventLedgerTest` mocking `Illuminate\Database\DatabaseManager`.
```php
<?php
namespace Tests\Unit\Laravel;

use Alama\LaravelArazzo\Laravel\DatabaseEventLedger;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use PHPUnit\Framework\TestCase;

class DatabaseEventLedgerTest extends TestCase
{
    public function test_appends_event_to_database(): void
    {
        $builder = $this->createMock(Builder::class);
        $builder->expects($this->once())->method('insert')->willReturn(true);
        
        $db = $this->createMock(DatabaseManager::class);
        $db->method('table')->with('arazzo_events')->willReturn($builder);
        
        $ledger = new DatabaseEventLedger($db, 'arazzo_events');
        $ledger->append('wf_1', 'StepExecuted', ['stepId' => 'A']);
    }
}
```

- [ ] **Step 2: Implement DatabaseEventLedger**
```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Illuminate\Database\DatabaseManager;

class DatabaseEventLedger implements EventLedgerInterface
{
    public function __construct(
        private DatabaseManager $db,
        private string $tableName = 'arazzo_events'
    ) {}

    public function append(string $workflowId, string $eventType, array $payload): void
    {
        $this->db->table($this->tableName)->insert([
            'workflow_id' => $workflowId,
            'event_type' => $eventType,
            'payload' => json_encode($payload),
            'created_at' => now(),
        ]);
    }
}
```

- [ ] **Step 3: Run test and commit**
Run `vendor/bin/phpunit tests/Unit/Laravel/DatabaseEventLedgerTest.php`
Commit: `feat: implement DatabaseEventLedger`

---

### Task 3: Definition Registry (Zero-Downtime Versioning)

**Files:**
- Create: `src/Execution/Contracts/DefinitionRegistryInterface.php`
- Create: `src/Execution/InMemoryDefinitionRegistry.php`
- Create: `tests/Unit/Execution/DefinitionRegistryTest.php`

- [ ] **Step 1: Define Interface and test**
The interface should have `register(Workflow $workflow): string` (returns hash) and `get(string $definitionId): ?Workflow`.
```php
<?php
namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Execution\InMemoryDefinitionRegistry;
use Alama\LaravelArazzo\Dto\Workflow;
use PHPUnit\Framework\TestCase;

class DefinitionRegistryTest extends TestCase
{
    public function test_registers_and_retrieves_workflow(): void
    {
        $registry = new InMemoryDefinitionRegistry();
        $wf = new Workflow('test_wf', '1.0', null, [], [], null); // Adjust constructor as needed for your DTO
        
        $id = $registry->register($wf);
        
        $this->assertNotNull($id);
        $this->assertSame($wf, $registry->get($id));
    }
}
```

- [ ] **Step 2: Implement InMemoryDefinitionRegistry**
```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\LaravelArazzo\Dto\Workflow;

class InMemoryDefinitionRegistry implements DefinitionRegistryInterface
{
    private array $registry = [];

    public function register(Workflow $workflow): string
    {
        // Simple hash for versioning based on workflow content. 
        // In real life, it might hash the source JSON/YAML, but here we can just use spl_object_hash or a uniqid for the MVP.
        $id = $workflow->workflowId . '_' . uniqid();
        $this->registry[$id] = $workflow;
        return $id;
    }

    public function get(string $definitionId): ?Workflow
    {
        return $this->registry[$definitionId] ?? null;
    }
}
```

- [ ] **Step 3: Run test and commit**
Run `vendor/bin/phpunit tests/Unit/Execution/DefinitionRegistryTest.php`
Commit: `feat: implement DefinitionRegistry for versioning`
