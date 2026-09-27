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
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Evaluation\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Runner\AsyncGraphSeams;
use Alama\Arazzo\Runner\Execution\AsyncExecutionGraphAssembler;
use Alama\Arazzo\Runner\Execution\OperationRuntime;
use Alama\Arazzo\Runner\Protocol\AsyncApiStepExecutor;
use Alama\Arazzo\Runtime\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\StateStoreInterface;
use Alama\Arazzo\Sources\SourceGraph;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

function seams(
    ?ClientInterface $httpClient = null,
    ?HttpClientInterface $httpClientInterface = null,
    ?LockManagerInterface $lockManager = null,
    ?QueueDriverInterface $queueDriver = null,
    ?ExpressionResolverInterface $expressionResolver = null,
    int $retryCeiling = 10,
    float $retryBackoffMultiplier = 1.0,
    int $stateTtlSeconds = 86400,
): AsyncGraphSeams {
    $stub = static fn (object $i): object => $i;

    // Create the HttpClientInterface for the seams
    $seamsHttpClient = $httpClientInterface ?? ($httpClient !== null
        ? new class($httpClient) implements HttpClientInterface
        {
            private ClientInterface $client;

            public function __construct(ClientInterface $client)
            {
                $this->client = $client;
            }

            public function sendRequest(RequestInterface $request, ?float $timeoutSeconds = null): ResponseInterface
            {
                return $this->client->sendRequest($request, $timeoutSeconds);
            }
        }
        : new class() implements HttpClientInterface
        {
            public function sendRequest(RequestInterface $request, ?float $timeoutSeconds = null): ResponseInterface
            {
                return new Response(200);
            }
        });

    return new AsyncGraphSeams(
        stateStore: $stub(new class() implements StateStoreInterface
        {
            public function save(string $executionId, array $state, ?int $ttlSeconds = null): void {}

            public function load(string $executionId): ?array
            {
                return null;
            }

            public function delete(string $executionId): void {}
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
            public function register(ArazzoDocument $document): string
            {
                return 'test-def';
            }

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
        httpClient: $seamsHttpClient,
        expressionResolver: $expressionResolver ?? $stub(new class() implements ExpressionResolverInterface
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
        }),
        requestFactory: $stub(new HttpFactory()),
        logger: null,
        idempotencyEnabled: false,
        idempotencyHeader: 'Idempotency-Key',
        strictValidation: false,
        retryCeiling: $retryCeiling,
        retryBackoffMultiplier: $retryBackoffMultiplier,
        stateTtlSeconds: $stateTtlSeconds,
    );
}

it('assembles graph with all core nodes', function () {
    $seams = seams();
    $runtime = SourceGraph::runtime();
    $assembler = new AsyncExecutionGraphAssembler(
        new OperationRuntime(
            $runtime->document,
            $runtime->operations,
        ),
        new EvaluationEngine(),
        new ExpressionEngine(),
        null,
        new HttpFactory(),
    );
    $graph = $assembler->assemble($seams);

    expect($graph->stepExecutor())->not->toBeNull()
        ->and($graph->workflowExecutor())->not->toBeNull()
        ->and($graph->outcomeHandler())->not->toBeNull()
        ->and($graph->resumer())->not->toBeNull()
        ->and($graph->worker())->not->toBeNull()
        ->and($graph->expressionResolver())->not->toBeNull()
        ->and($graph->protocolExecutors())->toHaveCount(3);
});

it('injects http client from seams into async executor', function () {
    $client = new class() implements ClientInterface
    {
        public function sendRequest(RequestInterface $request, ?float $timeoutSeconds = null): ResponseInterface
        {
            return new Response(201);
        }
    };
    $seams = seams(httpClient: $client);
    $runtime = SourceGraph::runtime();
    $assembler = new AsyncExecutionGraphAssembler(
        new OperationRuntime(
            $runtime->document,
            $runtime->operations,
        ),
        new EvaluationEngine(),
        new ExpressionEngine(),
        $client,
        new HttpFactory(),
    );
    $graph = $assembler->assemble($seams);

    $asyncExecutor = $graph->protocolExecutors()[2];
    expect($asyncExecutor)->toBeInstanceOf(AsyncApiStepExecutor::class);
});
