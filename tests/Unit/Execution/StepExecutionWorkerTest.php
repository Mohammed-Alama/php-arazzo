<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\InMemoryDefinitionRegistry;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Execution\StepExecutionWorker;
use Alama\LaravelArazzo\Execution\SyncQueueDriver;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class StepExecutionMockLockManager implements LockManagerInterface
{
    public int $acquireCount = 0;

    public ?string $lastLockKey = null;

    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $this->acquireCount++;
        $this->lastLockKey = $key;

        return $callback();
    }
}
class StepExecutionMockStateStore implements StateStoreInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $saves = [];

    /** @var array<string, int|null> */
    public array $ttls = [];

    public int $loadCount = 0;

    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
        $this->saves[$executionId] = $state;
        $this->ttls[$executionId] = $ttlSeconds;
    }

    public function load(string $executionId): ?array
    {
        $this->loadCount++;

        return $this->saves[$executionId] ?? null;
    }
}
class StepExecutionMockExpressionResolver implements ExpressionResolverInterface
{
    public ?ArazzoDocument $lastDocumentSeenByCompileRequest = null;

    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
    {
        $this->lastDocumentSeenByCompileRequest = $document;

        return new Request('GET', 'http://localhost');
    }

    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        return [];
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }
}
class StepExecutionMockHttpClient implements HttpClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return new Response(200);
    }
}
class StepExecutionMockQueueDriver implements QueueDriverInterface
{
    /** @var list<array{job: object, delaySeconds: int}> */
    public array $dispatched = [];

    public function dispatch(object $job, int $delaySeconds = 0): void
    {
        $this->dispatched[] = ['job' => $job, 'delaySeconds' => $delaySeconds];
    }
}
class StepExecutionMockEventLedger implements EventLedgerInterface
{
    /** @var list<array{executionId: string, eventType: string, payload: array<string, mixed>}> */
    public array $appended = [];

    public function append(string $executionId, string $eventType, array $payload): void
    {
        $this->appended[] = ['executionId' => $executionId, 'eventType' => $eventType, 'payload' => $payload];
    }
}
class StepExecutionMockExecutionRegistry implements ExecutionRegistryInterface
{
    /** @var list<array{executionId: string, definitionId: string, workflowId: string}> */
    public array $started = [];

    public function start(string $executionId, string $definitionId, string $workflowId): void
    {
        $this->started[] = ['executionId' => $executionId, 'definitionId' => $definitionId, 'workflowId' => $workflowId];
    }

    public function complete(string $executionId, \Alama\LaravelArazzo\Execution\ExecutionStatus $status): void
    {
    }
}

function makeStepExecutionWorkerDocument(Workflow $workflow): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [$workflow],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

it('skips an already-completed step', function (): void {
    $lockManager = new StepExecutionMockLockManager();
    $store = new StepExecutionMockStateStore();
    $resolver = new StepExecutionMockExpressionResolver();
    $client = new StepExecutionMockHttpClient();
    $queue = new SyncQueueDriver();
    $engine = new Engine(new DependencyAnalyzer(), $queue, $store);
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $eventLedger = new StepExecutionMockEventLedger();
    $executionRegistry = new StepExecutionMockExecutionRegistry();

    $worker = new StepExecutionWorker($lockManager, $store, $engine, $client, $resolver, $definitionRegistry, $eventLedger, $executionRegistry);

    $step = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
    $context = (new WorkflowContext('def_1'))->withExecutionId('exec_1')->withStepResult('A', ['success' => true]);

    $job = new ExecuteStepJob($step, $context);
    $worker->handle($job);

    expect($lockManager->acquireCount)->toBe(1);
    expect($lockManager->lastLockKey)->toBe('workflow_lock_exec_1');
    expect($store->saves)->toBeEmpty();
    expect($store->loadCount)->toBe(1);
    expect($eventLedger->appended)->toBeEmpty();
});

it('throws when the context has no executionId', function (): void {
    $lockManager = new StepExecutionMockLockManager();
    $store = new StepExecutionMockStateStore();
    $resolver = new StepExecutionMockExpressionResolver();
    $client = new StepExecutionMockHttpClient();
    $queue = new SyncQueueDriver();
    $engine = new Engine(new DependencyAnalyzer(), $queue, $store);
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $eventLedger = new StepExecutionMockEventLedger();
    $executionRegistry = new StepExecutionMockExecutionRegistry();

    $worker = new StepExecutionWorker($lockManager, $store, $engine, $client, $resolver, $definitionRegistry, $eventLedger, $executionRegistry);

    $step = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
    $context = new WorkflowContext('def_1'); // no executionId set

    $job = new ExecuteStepJob($step, $context);

    expect(fn () => $worker->handle($job))->toThrow(\LogicException::class);
});

it('appends a definition_missing event when the registry returns null', function (): void {
    $lockManager = new StepExecutionMockLockManager();
    $store = new StepExecutionMockStateStore();
    $resolver = new StepExecutionMockExpressionResolver();
    $client = new StepExecutionMockHttpClient();
    $queue = new SyncQueueDriver();
    $engine = new Engine(new DependencyAnalyzer(), $queue, $store);
    $definitionRegistry = new InMemoryDefinitionRegistry(); // nothing registered
    $eventLedger = new StepExecutionMockEventLedger();
    $executionRegistry = new StepExecutionMockExecutionRegistry();

    $worker = new StepExecutionWorker($lockManager, $store, $engine, $client, $resolver, $definitionRegistry, $eventLedger, $executionRegistry);

    $step = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
    $context = (new WorkflowContext('missing_def'))->withExecutionId('exec_1');

    $job = new ExecuteStepJob($step, $context);
    $worker->handle($job);

    expect($eventLedger->appended)->toHaveCount(1);
    expect($eventLedger->appended[0]['eventType'])->toBe('execution.definition_missing');
    expect($store->saves)->toBeEmpty();
});

it('executes a step, saves state with TTL, appends an event, and starts the execution', function (): void {
    $lockManager = new StepExecutionMockLockManager();
    $store = new StepExecutionMockStateStore();
    $resolver = new StepExecutionMockExpressionResolver();
    $client = new StepExecutionMockHttpClient();
    $queue = new SyncQueueDriver();
    $engine = new Engine(new DependencyAnalyzer(), $queue, $store);
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $eventLedger = new StepExecutionMockEventLedger();
    $executionRegistry = new StepExecutionMockExecutionRegistry();

    $step = new Step('B', null, null, null, null, [], null, [], [], [], [], []);
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = makeStepExecutionWorkerDocument($workflow);
    $definitionId = $definitionRegistry->register($document);

    $worker = new StepExecutionWorker(
        $lockManager, $store, $engine, $client, $resolver, $definitionRegistry, $eventLedger, $executionRegistry,
        stateTtlSeconds: 3600,
    );

    $context = (new WorkflowContext($definitionId))->withExecutionId('exec_1')->withWorkflowId('wf_1');

    $job = new ExecuteStepJob($step, $context);
    $worker->handle($job);

    expect($store->saves)->toHaveKey('exec_1');
    expect($store->ttls['exec_1'])->toBe(3600);
    expect($store->saves['exec_1']['steps'])->toHaveKey('B');

    expect($eventLedger->appended)->toHaveCount(1);
    expect($eventLedger->appended[0]['eventType'])->toBe('step.executed');
    expect($eventLedger->appended[0]['executionId'])->toBe('exec_1');

    expect($executionRegistry->started)->toHaveCount(1);
    expect($executionRegistry->started[0]['executionId'])->toBe('exec_1');
    expect($executionRegistry->started[0]['workflowId'])->toBe('wf_1');

    // compileRequest/extractOutputs should have received the real document, not null.
    expect($resolver->lastDocumentSeenByCompileRequest)->not->toBeNull();
    expect($resolver->lastDocumentSeenByCompileRequest)->toBe($document);
});

it('dispatches a newly-unlocked downstream step after success', function (): void {
    $lockManager = new StepExecutionMockLockManager();
    $store = new StepExecutionMockStateStore();
    $resolver = new StepExecutionMockExpressionResolver();
    $client = new StepExecutionMockHttpClient();
    $queue = new SyncQueueDriver();
    $engine = new Engine(new DependencyAnalyzer(), $queue, $store);
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $eventLedger = new StepExecutionMockEventLedger();
    $executionRegistry = new StepExecutionMockExecutionRegistry();

    $stepA = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
    $stepB = new Step('B', null, null, null, null, [], null, [], [], [], [], ['A']);
    $workflow = new Workflow('wf_1', null, null, null, [], [$stepA, $stepB], [], [], [], []);
    $document = makeStepExecutionWorkerDocument($workflow);
    $definitionId = $definitionRegistry->register($document);

    $worker = new StepExecutionWorker($lockManager, $store, $engine, $client, $resolver, $definitionRegistry, $eventLedger, $executionRegistry);

    $context = (new WorkflowContext($definitionId))->withExecutionId('exec_1')->withWorkflowId('wf_1');

    $job = new ExecuteStepJob($stepA, $context);
    $worker->handle($job);

    expect($queue->dispatched)->toHaveCount(1);
    $dispatchedJob = $queue->dispatched[0]['job'];
    expect($dispatchedJob->step->stepId)->toBe('B');
});
