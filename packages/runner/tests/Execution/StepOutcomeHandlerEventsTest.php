<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Action\FailureEndAction;
use Alama\Arazzo\Contracts\Spec\Action\RetryAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessEndAction;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\PendingCorrelation;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Contracts\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Evaluation\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\Events\RunCompletedEvent;
use Alama\Arazzo\Runner\Events\RunFailedEvent;
use Alama\Arazzo\Runner\Events\StepRetriedEvent;
use Alama\Arazzo\Runner\Execution\Data\RunControlFlow;
use Alama\Arazzo\Runner\Execution\Data\RunPersistence;
use Alama\Arazzo\Runner\Execution\StepOutcomeHandler;
use Alama\Arazzo\Runner\Execution\SubWorkflowInvoker;
use Alama\Arazzo\Runner\Execution\SyncQueueDriver;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runtime\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\StateStoreInterface;
use Alama\Arazzo\Tests\Expression\Support\TestExpressionResolver;

class OutcomeEventsMockStateStore implements StateStoreInterface
{
    public array $saves = [];

    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
        $this->saves[$executionId] = $state;
    }

    public function load(string $executionId): ?array
    {
        return $this->saves[$executionId] ?? null;
    }

    public function delete(string $executionId): void {}
}

class OutcomeEventsMockEventLedger implements EventLedgerInterface
{
    public array $appended = [];

    public function append(string $executionId, string $eventType, array $payload): void
    {
        $this->appended[] = ['executionId' => $executionId, 'eventType' => $eventType, 'payload' => $payload];
    }
}

class OutcomeEventsMockExecutionRegistry implements ExecutionRegistryInterface
{
    public function start(string $executionId, string $definitionId, string $workflowId): void {}

    public function complete(string $executionId, \Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus $status): void {}
}

class OutcomeEventsMockDefinitionRegistry implements \Alama\Arazzo\Runtime\State\Interfaces\DefinitionRegistryInterface
{
    public function register(\Alama\Arazzo\Contracts\Spec\ArazzoDocument $document): string { return 'test-def'; }
    public function get(string $definitionId): ?\Alama\Arazzo\Contracts\Spec\ArazzoDocument { return null; }
}

class OutcomeEventsMockPendingCorrelations implements PendingCorrelationRegistryInterface
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

class OutcomeEventsMockExpressionResolver implements ExpressionResolverInterface
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

class OutcomeEventsMockLockManager implements \Alama\Arazzo\Contracts\Interfaces\LockManagerInterface
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

class OutcomeEventsMockQueueDriver implements \Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface
{
    /** @var list<object> */
    public array $dispatched = [];

    public function dispatch(object $job, int $delaySeconds = 0): void
    {
        $this->dispatched[] = $job;
    }
}
