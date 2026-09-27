<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\PendingCorrelation;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Evaluation\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\AsyncGraphSeams;
use Alama\Arazzo\Runtime\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\StateStoreInterface;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

function dummySeams(): AsyncGraphSeams
{
    return new AsyncGraphSeams(
        stateStore: new class() implements StateStoreInterface
        {
            public function save(string $executionId, array $state, ?int $ttlSeconds = null): void {}

            public function load(string $executionId): ?array
            {
                return null;
            }

            public function delete(string $executionId): void {}
        },
        queueDriver: new class() implements QueueDriverInterface
        {
            public function dispatch(object $job, int $delaySeconds = 0): void {}
        },
        eventLedger: new class() implements EventLedgerInterface
        {
            public function append(string $executionId, string $eventType, array $payload): void {}
        },
        executionRegistry: new class() implements ExecutionRegistryInterface
        {
            public function start(string $executionId, string $definitionId, string $workflowId): void {}

            public function complete(string $executionId, ExecutionStatus $status): void {}
        },
        pendingCorrelationRegistry: new class() implements PendingCorrelationRegistryInterface
        {
            public function create(string $correlationId, string $executionId, string $stepId, string $channelPath, ?int $timeoutSeconds = null): void {}

            public function findByCorrelationId(string $correlationId): ?PendingCorrelation
            {
                return null;
            }

            public function consume(string $correlationId): void {}

            public function existsForExecution(string $executionId): bool
            {
                return false;
            }
        },
        definitionRegistry: new class() implements DefinitionRegistryInterface
        {
            public function register(ArazzoDocument $document): string
            {
                return 'test-def';
            }

            public function get(string $definitionId): ?ArazzoDocument
            {
                return null;
            }
        },
        lockManager: new class() implements LockManagerInterface
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
        },
        httpClient: new class() implements HttpClientInterface
        {
            public function sendRequest(RequestInterface $request, ?float $timeoutSeconds = null): ResponseInterface
            {
                return new Response(200);
            }
        },
        expressionResolver: new class() implements ExpressionResolverInterface
        {
            public function evaluate(Expression $expression, WorkflowContextInterface $context, ?string $currentStepId = null): mixed
            {
                return $expression->raw;
            }

            public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void {}

            public function extractOutputs(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): array
            {
                return [];
            }

            public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
            {
                return true;
            }

            public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
            {
                return true;
            }
        },
        requestFactory: new HttpFactory(),
        logger: null,
        idempotencyEnabled: false,
        idempotencyHeader: 'Idempotency-Key',
        strictValidation: false,
        retryCeiling: 10,
        retryBackoffMultiplier: 1.0,
        stateTtlSeconds: 86400,
    );
}

it('constructs seams with all required ports', function () {
    $seams = dummySeams();

    expect($seams->stateStore)->not->toBeNull()
        ->and($seams->queueDriver)->not->toBeNull()
        ->and($seams->eventLedger)->not->toBeNull()
        ->and($seams->executionRegistry)->not->toBeNull()
        ->and($seams->pendingCorrelationRegistry)->not->toBeNull()
        ->and($seams->definitionRegistry)->not->toBeNull()
        ->and($seams->lockManager)->not->toBeNull()
        ->and($seams->httpClient)->not->toBeNull()
        ->and($seams->expressionResolver)->not->toBeNull()
        ->and($seams->requestFactory)->not->toBeNull();
});

it('retains config knobs', function () {
    $seams = dummySeams();

    expect($seams->idempotencyEnabled)->toBeFalse()
        ->and($seams->idempotencyHeader)->toBe('Idempotency-Key')
        ->and($seams->strictValidation)->toBeFalse()
        ->and($seams->retryCeiling)->toBe(10)
        ->and($seams->retryBackoffMultiplier)->toBe(1.0)
        ->and($seams->stateTtlSeconds)->toBe(86400);
});
