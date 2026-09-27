<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\StepProtocolExecutorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\PendingCorrelation;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Contracts\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Evaluation\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Events\CorrelationPendingEvent;
use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\Events\StepExecutedEvent as EventStepExecuted;
use Alama\Arazzo\Runner\Events\StepFailedEvent;
use Alama\Arazzo\Runner\Events\StepStartedEvent;
use Alama\Arazzo\Runner\Execution\Data\RunControlFlow;
use Alama\Arazzo\Runner\Execution\Data\RunPersistence;
use Alama\Arazzo\Runner\Execution\InMemoryDefinitionRegistry;
use Alama\Arazzo\Runner\Execution\StepExecutionWorker;
use Alama\Arazzo\Runner\Execution\StepOutcomeHandler;
use Alama\Arazzo\Runner\Execution\SubWorkflowInvoker;
use Alama\Arazzo\Runner\Execution\SyncQueueDriver;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Jobs\ExecuteStepJob;
use Alama\Arazzo\Runtime\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\StateStoreInterface;

class WorkerEventsMockLockManager implements LockManagerInterface
{
    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return $callback();
    }

    public function tryAcquire(string $key, int $ttlSeconds): bool
    {
        return true;
    }

    public function release(string $key): void {}
}

class WorkerEventsMockStateStore implements StateStoreInterface
{
    public array $saves = [];

    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
        $this->saves[$executionId] = $state;
    }

    public function load(string $executionId): ?array
    {
        return null;
    }

    public function delete(string $executionId): void {}
}

class WorkerEventsMockEventLedger implements EventLedgerInterface
{
    /** @var list<array{executionId: string, eventType: string, payload: array<string, mixed>}> */
    public array $appended = [];

    public function append(string $executionId, string $eventType, array $payload): void
    {
        $this->appended[] = ['executionId' => $executionId, 'eventType' => $eventType, 'payload' => $payload];
    }
}

class WorkerEventsMockQueueDriver implements \Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface
{
    /** @var list<object> */
    public array $dispatched = [];

    public function dispatch(object $job, int $delaySeconds = 0): void
    {
        $this->dispatched[] = $job;
    }
}

class WorkerEventsMockPendingCorrelations implements PendingCorrelationRegistryInterface
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

class WorkerEventsMockExecutionRegistry implements ExecutionRegistryInterface
{
    public function start(string $executionId, string $definitionId, string $workflowId): void {}

    public function complete(string $executionId, \Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus $status): void {}
}

class WorkerEventsMockDefinitionRegistry implements \Alama\Arazzo\Runtime\State\Interfaces\DefinitionRegistryInterface
{
    public function register(\Alama\Arazzo\Contracts\Spec\ArazzoDocument $document): string { return 'test-def'; }
    public function get(string $definitionId): ?\Alama\Arazzo\Contracts\Spec\ArazzoDocument { return null; }
}

class WorkerEventsMockExpressionResolver implements ExpressionResolverInterface
{
    public function evaluate(\Alama\Arazzo\Contracts\Spec\Expression $expression, \Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface $context, ?string $currentStepId = null): mixed
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
