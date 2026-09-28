<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\PendingCorrelation;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\Data\EvaluationContext;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Evaluation\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Events\Interfaces\EventLedgerInterface;
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

class WorkerMockExpressionResolver implements EvaluationEngineInterface
{
    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
    {
        return $expression->raw;
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed
    {
        return null;
    }

    public function queryXPath(mixed $rootValue, string $selector, string $version): mixed
    {
        return null;
    }

    public function supportedXPathVersions(): array
    {
        return [];
    }

    public function interpolate(string $value, WorkflowContextInterface $context, string $stepId): string
    {
        return $value;
    }

    public function resolveValue(mixed $value, WorkflowContextInterface $context, ?string $stepId = null): mixed
    {
        if (is_string($value)) {
            return $this->interpolate($value, $context, $stepId ?? '');
        }

        if ($value instanceof Expression) {
            return $this->evaluate($value, new EvaluationContext($context, $stepId));
        }

        if ($value instanceof Selector) {
            return $this->evaluateSelector($value, $context, $stepId ?? '');
        }

        return $value;
    }

    public function replacePayload(Step $step, array $body, ?callable $resolveValue = null, ?WorkflowContext $context = null): array
    {
        return $body;
    }

    public function jsonPath(string $expression, array|object $data): mixed
    {
        return null;
    }

    public function jsonPointer(array $data, ?string $pointer): mixed
    {
        return null;
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

class WorkerMockQueueDriver implements QueueDriverInterface
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
    public ?PendingCorrelation $toReturn = null;

    /** @var list<string> */
    public array $consumed = [];

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath, ?int $timeoutSeconds = null): void {}

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

class WorkerMockExecutionRegistry implements ExecutionRegistryInterface
{
    public function start(string $executionId, string $definitionId, string $workflowId): void {}

    public function complete(string $executionId, ExecutionStatus $status): void {}
}

class WorkerMockDefinitionRegistry implements DefinitionRegistryInterface
{
    public function register(ArazzoDocument $document): string
    {
        return 'test-def';
    }

    public function get(string $definitionId): ?ArazzoDocument
    {
        return null;
    }
}
