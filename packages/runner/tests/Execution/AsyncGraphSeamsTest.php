<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus;
use Alama\Arazzo\Contracts\Spec\PendingCorrelation;
use Alama\Arazzo\Runner\AsyncGraphSeams;
use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\Infrastructure\Interfaces\HttpClientInterface;
use Alama\Arazzo\Runner\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\StateStoreInterface;
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
    );
}

it('defaults config knobs for an empty seams bag', function (): void {
    $seams = dummySeams();

    expect($seams->idempotencyEnabled)->toBeFalse()
        ->and($seams->idempotencyHeader)->toBe('Idempotency-Key')
        ->and($seams->strictValidation)->toBeFalse()
        ->and($seams->retryCeiling)->toBe(10)
        ->and($seams->retryBackoffMultiplier)->toBe(1.0)
        ->and($seams->stateTtlSeconds)->toBe(86400);
});
