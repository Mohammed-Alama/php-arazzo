<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Contracts\Interfaces\StepProtocolExecutorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus;
use Alama\Arazzo\Contracts\Spec\PendingCorrelation;
use Alama\Arazzo\Document\Document;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\AsyncGraphSeams;
use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\Execution\AsyncExecutionGraphAssembler;
use Alama\Arazzo\Runner\Execution\CorrelationResumer;
use Alama\Arazzo\Runner\Execution\StepExecutionWorker;
use Alama\Arazzo\Runner\Execution\StepOutcomeHandler;
use Alama\Arazzo\Runner\Infrastructure\Interfaces\HttpClientInterface;
use Alama\Arazzo\Runner\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\StateStoreInterface;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

function seams(
    ?HttpClientInterface $httpClient = null,
    ?LockManagerInterface $lockManager = null,
    ?QueueDriverInterface $queueDriver = null,
    ?ExpressionResolverInterface $expressionResolver = null,
    int $retryCeiling = 10,
    float $retryBackoffMultiplier = 1.0,
    int $stateTtlSeconds = 86400,
): AsyncGraphSeams {
    $stub = static fn (object $i): object => $i;

    return new AsyncGraphSeams(
        stateStore: $stub(new class() implements StateStoreInterface
        {
            public function save(string $executionId, array $state, ?int $ttlSeconds = null): void {}

            public function load(string $executionId): ?array
            {
                return null;
            }
        }),
        queueDriver: $queueDriver ?? $stub(new class() implements QueueDriverInterface
        {
            public function dispatch(object $job, int $delaySeconds = 0): void {}
        }),
        eventLedger: $stub(new class() implements EventLedgerInterface
        {
            public function append(string $executionId, string $eventType, array $payload): void {}
        }),
        executionRegistry: $stub(new class() implements ExecutionRegistryInterface
        {
            public function start(string $executionId, string $definitionId, string $workflowId): void {}

            public function complete(string $executionId, ExecutionStatus $status): void {}
        }),
        pendingCorrelationRegistry: $stub(new class() implements PendingCorrelationRegistryInterface
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
        }),
        definitionRegistry: $stub(new class() implements DefinitionRegistryInterface
        {
            public function get(string $definitionId): ?ArazzoDocument
            {
                return null;
            }
        }),
        lockManager: $lockManager ?? $stub(new class() implements LockManagerInterface
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
        }),
        httpClient: $httpClient ?? $stub(new class() implements HttpClientInterface
        {
            public function sendRequest(RequestInterface $request, ?float $timeoutSeconds = null): ResponseInterface
            {
                return new Response(200);
            }
        }),
        expressionResolver: $expressionResolver,
        requestFactory: new HttpFactory(),
        retryCeiling: $retryCeiling,
        retryBackoffMultiplier: $retryBackoffMultiplier,
        stateTtlSeconds: $stateTtlSeconds,
    );
}

function internal(AsyncGraphSeams $s): AsyncExecutionGraphAssembler
{
    return new AsyncExecutionGraphAssembler(
        new Document(),
        new ExpressionEngine(),
    );
}

it('wires every node and shares one workflow executor across the graph', function (): void {
    $graph = internal(seams())->assemble(seams());

    expect($graph->worker())->toBeInstanceOf(StepExecutionWorker::class)
        ->and($graph->resumer())->toBeInstanceOf(CorrelationResumer::class)
        ->and($graph->outcomeHandler())->toBeInstanceOf(StepOutcomeHandler::class)
        ->and($graph->workflowExecutor())->toBe($graph->workflowExecutor())
        ->and($graph->expressionResolver())->toBeInstanceOf(ExpressionResolverInterface::class)
        ->and($graph->protocolExecutors())->toHaveCount(3);
});

it('passes retry knobs and state ttl into the engine and worker', function (): void {
    $graph = internal(seams())->assemble(seams(retryCeiling: 7, retryBackoffMultiplier: 2.5, stateTtlSeconds: 60));

    $workerTtl = new ReflectionProperty($graph->worker(), 'stateTtlSeconds');

    expect($workerTtl->getValue($graph->worker()))->toBe(60);
});

it('orders protocol executors subworkflow, http, async', function (): void {
    $graph = internal(seams())->assemble(seams());

    $classes = array_map(fn (StepProtocolExecutorInterface $e): string => (string) (new ReflectionClass($e))->getShortName(), $graph->protocolExecutors());

    expect($classes)->toBe(['SubWorkflowStepExecutor', 'HttpStepExecutor', 'AsyncApiStepExecutor']);
});

it('uses a provided expression resolver instead of building one', function (): void {
    $resolver = Mockery::mock(ExpressionResolverInterface::class);
    $graph = internal(seams())->assemble(seams(expressionResolver: $resolver));

    expect($graph->expressionResolver())->toBe($resolver);
});
