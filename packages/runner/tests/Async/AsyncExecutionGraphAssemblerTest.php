<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Interfaces\OutputExtractorInterface;
use Alama\Arazzo\Contracts\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus;
use Alama\Arazzo\Contracts\Spec\PendingCorrelation;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Async\AsyncExecutionGraphAssembler;
use Alama\Arazzo\Runner\AsyncGraphSeams;
use Alama\Arazzo\Runner\Execution\OperationRuntime;
use Alama\Arazzo\Runner\OperationExecutorRegistry;
use Alama\Arazzo\Runner\Sync\SubWorkflowStepExecutor;
use Alama\Arazzo\Runtime\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\StateStoreInterface;
use Alama\Arazzo\Sources\SourceGraph;
use Alama\Arazzo\Tests\Support\FakeOpenApiExecutor;
use Alama\Arazzo\Tests\Support\FakeOutputExtractor;
use Alama\Arazzo\Tests\Support\FakeResponseValidator;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__.'/../Support/FakeSeams.php';

function seams(
    ?ClientInterface $httpClient = null,
    ?HttpClientInterface $httpClientInterface = null,
    ?LockManagerInterface $lockManager = null,
    ?QueueDriverInterface $queueDriver = null,
    ?ResponseValidatorInterface $schemaValidator = null,
    ?OutputExtractorInterface $outputExtractor = null,
    ?OpenApiExecutorInterface $openApiExecutor = null,
    ?OperationExecutorRegistry $executorRegistry = null,
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
        schemaValidator: $schemaValidator ?? new FakeResponseValidator(),
        outputExtractor: $outputExtractor ?? new FakeOutputExtractor(),
        openApiExecutor: $openApiExecutor ?? new FakeOpenApiExecutor(),
        requestFactory: $stub(new HttpFactory()),
        logger: null,
        idempotencyEnabled: false,
        idempotencyHeader: 'Idempotency-Key',
        strictValidation: false,
        retryCeiling: $retryCeiling,
        retryBackoffMultiplier: $retryBackoffMultiplier,
        stateTtlSeconds: $stateTtlSeconds,
        executorRegistry: $executorRegistry,
    );
}

/**
 * A registry plugin stand-in for the host-registered HTTP/AsyncAPI executors:
 * the assembler no longer constructs protocol executors itself — hosts
 * register them (F1.3's registrar does so in Laravel).
 */
function stubProtocolPlugin(string $pluginName, int $pluginPriority = 100): OperationExecutorPluginInterface
{
    return new class($pluginName, $pluginPriority) implements OperationExecutorPluginInterface
    {
        public function __construct(
            private readonly string $pluginName,
            private readonly int $pluginPriority,
        ) {}

        public function name(): string
        {
            return $this->pluginName;
        }

        public function priority(): int
        {
            return $this->pluginPriority;
        }

        public function supports(Step $step, ArazzoDocument $document): bool
        {
            return true;
        }

        public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
        {
            throw new LogicException('stub protocol plugin is never executed');
        }
    };
}

function hostRegistry(OperationExecutorPluginInterface ...$plugins): OperationExecutorRegistry
{
    $registry = new OperationExecutorRegistry();
    foreach ($plugins as $plugin) {
        $registry->register($plugin);
    }

    return $registry;
}

it('assembles graph with all core nodes', function () {
    $seams = seams(executorRegistry: hostRegistry(
        stubProtocolPlugin('http', 100),
        stubProtocolPlugin('asyncapi', 200),
    ));
    $runtime = SourceGraph::runtime();
    $assembler = new AsyncExecutionGraphAssembler(
        new OperationRuntime(
            $runtime->document,
            $runtime->operations,
        ),
        new EvaluationEngine(expression: new ExpressionEngine()),
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
        ->and($graph->protocolExecutors())->toHaveCount(3);
});

it('flows host-registered protocol executors into the graph alongside the composed sub-workflow executor', function () {
    $httpPlugin = stubProtocolPlugin('http', 100);
    $asyncPlugin = stubProtocolPlugin('asyncapi', 200);
    $seams = seams(executorRegistry: hostRegistry($httpPlugin, $asyncPlugin));
    $runtime = SourceGraph::runtime();
    $assembler = new AsyncExecutionGraphAssembler(
        new OperationRuntime(
            $runtime->document,
            $runtime->operations,
        ),
        new EvaluationEngine(expression: new ExpressionEngine()),
        new ExpressionEngine(),
        null,
        new HttpFactory(),
    );
    $graph = $assembler->assemble($seams);

    $executors = $graph->protocolExecutors();
    expect($executors)->toHaveCount(3)
        ->toContain($httpPlugin)
        ->toContain($asyncPlugin);
    expect(array_find($executors, fn ($executor): bool => $executor instanceof SubWorkflowStepExecutor))
        ->not->toBeNull();
});
