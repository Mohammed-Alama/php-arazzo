<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\StepProtocolExecutorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus;
use Alama\Arazzo\Contracts\Spec\Enum\StepStatus;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\PendingCorrelation;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Contracts\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Evaluation\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\Events\Listener\LedgerEventListener;
use Alama\Arazzo\Runner\Execution\Data\RunControlFlow;
use Alama\Arazzo\Runner\Execution\Data\RunPersistence;
use Alama\Arazzo\Runner\Execution\InMemoryDefinitionRegistry;
use Alama\Arazzo\Runner\Execution\StepExecutionWorker;
use Alama\Arazzo\Runner\Execution\StepOutcomeHandler;
use Alama\Arazzo\Runner\Execution\SubWorkflowInvoker;
use Alama\Arazzo\Runner\Execution\SyncQueueDriver;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Jobs\ExecuteStepJob;
use Alama\Arazzo\Runtime\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\StateStoreInterface;

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

    public function tryAcquire(string $key, int $ttlSeconds): bool
    {
        $this->acquireCount++;
        $this->keysUsed[] = $key;

        return true;
    }

    public function release(string $key): void {}
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
        return $this->preloaded[$executionId] ?? $this->saves[$executionId] ?? null;
    }

    public function delete(string $executionId): void
    {
        unset($this->preloaded[$executionId]);
        unset($this->saves[$executionId]);
    }
}

class WorkerMockExpressionResolver implements ExpressionResolverInterface
{
    public function evaluate(Expression $expression, WorkflowContextInterface $context, ?string $currentStepId = null): mixed
    {
        return $expression->raw;
    }

    public function validateResponseSchema(\Alama\Arazzo\Contracts\Spec\Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?\Alama\Arazzo\Contracts\Spec\ArazzoDocument $document = null): void {}

    public function extractOutputs(\Alama\Arazzo\Contracts\Spec\Step $step, \Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface $context, ?\Alama\Arazzo\Contracts\Spec\ArazzoDocument $document = null): array
    {
        return [];
    }

    public function evaluateSuccessCriteria(\Alama\Arazzo\Contracts\Spec\Step $step, \Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface $context, ?\Alama\Arazzo\Contracts\Spec\ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateCriteria(array $criteria, \Alama\Arazzo\Contracts\Spec\Step $step, \Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface $context, ?\Alama\Arazzo\Contracts\Spec\ArazzoDocument $document = null): bool
    {
        return true;
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

class WorkerMockQueueDriver implements \Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface
{
    /** @var list<object> */
    public array $dispatched = [];

    public function dispatch(object $job, int $delaySeconds = 0): void
    {
        $this->dispatched[] = $job;
    }
}

class WorkerMockPendingCorrelations implements PendingCorrelationRegistryInterface
{
    public ?\Alama\Arazzo\Contracts\Spec\PendingCorrelation $toReturn = null;

    /** @var list<string> */
    public array $consumed = [];

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath, ?int $timeoutSeconds = null): void {}

    public function findByCorrelationId(string $correlationId): ?\Alama\Arazzo\Contracts\Spec\PendingCorrelation
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

class WorkerMockExecutionRegistry implements ExecutionRegistryInterface
{
    public function start(string $executionId, string $definitionId, string $workflowId): void {}

    public function complete(string $executionId, \Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus $status): void {}
}

class WorkerMockDefinitionRegistry implements DefinitionRegistryInterface
{
    public function register(\Alama\Arazzo\Contracts\Spec\ArazzoDocument $document): string { return 'test-def'; }
    public function get(string $definitionId): ?\Alama\Arazzo\Contracts\Spec\ArazzoDocument { return null; }
}
