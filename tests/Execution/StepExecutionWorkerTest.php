<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Contracts\StepProtocolExecutorInterface;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\ExecutionStatus;
use Alama\LaravelArazzo\Execution\InMemoryDefinitionRegistry;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Execution\PendingCorrelation;
use Alama\LaravelArazzo\Execution\StepExecutionOutcome;
use Alama\LaravelArazzo\Execution\StepExecutionWorker;
use Alama\LaravelArazzo\Execution\StepOutcomeHandler;
use Alama\LaravelArazzo\Execution\StepStatus;
use Alama\LaravelArazzo\Execution\SyncQueueDriver;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Psr\Http\Message\RequestInterface;

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
        return $this->preloaded[$executionId] ?? null;
    }
}

class WorkerMockExpressionResolver implements ExpressionResolverInterface
{
    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
    {
        throw new \LogicException('not used -- protocol dispatch is faked directly in these tests');
    }

    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        return [];
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return $criteria === [];
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

class WorkerMockExecutionRegistry implements ExecutionRegistryInterface
{
    /** @var list<array{executionId: string, definitionId: string, workflowId: string}> */
    public array $started = [];
    /** @var list<array{executionId: string, status: ExecutionStatus}> */
    public array $completed = [];

    public function start(string $executionId, string $definitionId, string $workflowId): void
    {
        $this->started[] = ['executionId' => $executionId, 'definitionId' => $definitionId, 'workflowId' => $workflowId];
    }

    public function complete(string $executionId, ExecutionStatus $status): void
    {
        $this->completed[] = ['executionId' => $executionId, 'status' => $status];
    }
}

class WorkerMockPendingCorrelationRegistry implements PendingCorrelationRegistryInterface
{
    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void
    {
    }

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        return null;
    }

    public function consume(string $correlationId): void
    {
    }

    public function existsForExecution(string $executionId): bool
    {
        return false;
    }
}

class WorkerFakeProtocolExecutor implements StepProtocolExecutorInterface
{
    public function __construct(private StepExecutionOutcome $outcome)
    {
    }

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return true;
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        return $this->outcome;
    }
}

function makeWorkerDocument(Workflow $workflow): ArazzoDocument
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

/**
 * @return array{0: StepExecutionWorker, 1: WorkerMockLockManager, 2: WorkerMockStateStore, 3: WorkerMockEventLedger, 4: WorkerMockExecutionRegistry, 5: SyncQueueDriver}
 */
function makeWorker(StepExecutionOutcome $outcome, \Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface $definitionRegistry): array
{
    $lockManager = new WorkerMockLockManager();
    $store = new WorkerMockStateStore();
    $eventLedger = new WorkerMockEventLedger();
    $executionRegistry = new WorkerMockExecutionRegistry();
    $resolver = new WorkerMockExpressionResolver();
    $queue = new SyncQueueDriver();
    $dependencyAnalyzer = new DependencyAnalyzer();
    $engine = new Engine($dependencyAnalyzer, $queue, $store);
    $outcomeHandler = new StepOutcomeHandler(
        $queue, $engine, $dependencyAnalyzer, $executionRegistry, $eventLedger,
        new WorkerMockPendingCorrelationRegistry(), $resolver, $store
    );

    $worker = new StepExecutionWorker(
        $lockManager, $store, $definitionRegistry, $eventLedger, $executionRegistry, $resolver,
        [new WorkerFakeProtocolExecutor($outcome)], $outcomeHandler,
    );

    return [$worker, $lockManager, $store, $eventLedger, $executionRegistry, $queue];
}

it('skips a step already at Succeeded status', function (): void {
    [$worker, $lockManager, $store, $eventLedger] = makeWorker(
        StepExecutionOutcome::resolved(200, [], []),
        new InMemoryDefinitionRegistry(),
    );

    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $context = (new WorkflowContext('def_1'))
        ->withExecutionId('exec_1')
        ->withStepResult('A', ['success' => true])
        ->withStepStatus('A', StepStatus::Succeeded);

    $worker->handle(new ExecuteStepJob($step, $context));

    expect($lockManager->acquireCount)->toBe(1);
    expect($store->saves)->toBeEmpty();
    expect($eventLedger->appended)->toBeEmpty();
});

it('throws when the context has no executionId', function (): void {
    [$worker] = makeWorker(StepExecutionOutcome::resolved(200, [], []), new InMemoryDefinitionRegistry());

    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $context = new WorkflowContext('def_1');

    expect(fn () => $worker->handle(new ExecuteStepJob($step, $context)))->toThrow(\LogicException::class);
});

it('appends a definition_missing event when the registry returns null', function (): void {
    [$worker, , $store, $eventLedger] = makeWorker(
        StepExecutionOutcome::resolved(200, [], []),
        new InMemoryDefinitionRegistry(),
    );

    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $context = (new WorkflowContext('missing_def'))->withExecutionId('exec_1');

    $worker->handle(new ExecuteStepJob($step, $context));

    expect($eventLedger->appended)->toHaveCount(1);
    expect($eventLedger->appended[0]['eventType'])->toBe('execution.definition_missing');
    expect($store->saves)->toBeEmpty();
});

it('appends a workflow_missing event when the context workflowId is not in the document', function (): void {
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $workflow = new Workflow('wf_1', null, null, null, [], [], [], [], [], []);
    $document = makeWorkerDocument($workflow);
    $definitionId = $definitionRegistry->register($document);

    [$worker, , , $eventLedger] = makeWorker(StepExecutionOutcome::resolved(200, [], []), $definitionRegistry);

    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $context = (new WorkflowContext($definitionId))->withExecutionId('exec_1')->withWorkflowId('wf_does_not_exist');

    $worker->handle(new ExecuteStepJob($step, $context));

    expect($eventLedger->appended[0]['eventType'])->toBe('execution.workflow_missing');
});

it('executes a step, saves state with TTL, appends step.executed, starts the execution, and continues via StepOutcomeHandler', function (): void {
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $stepA = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $stepB = new Step('B', null, null, null, null, [], null, [], [], [], [], ['A']);
    $workflow = new Workflow('wf_1', null, null, null, [], [$stepA, $stepB], [], [], [], []);
    $document = makeWorkerDocument($workflow);
    $definitionId = $definitionRegistry->register($document);

    [$worker, , $store, $eventLedger, $executionRegistry, $queue] = makeWorker(
        StepExecutionOutcome::resolved(200, ['id' => 1], ['id' => 1]),
        $definitionRegistry,
    );

    $context = (new WorkflowContext($definitionId))->withExecutionId('exec_1')->withWorkflowId('wf_1');

    $worker->handle(new ExecuteStepJob($stepA, $context));

    expect($store->saves)->toHaveKey('exec_1');
    expect($store->saves['exec_1']['steps'])->toHaveKey('A');
    expect($eventLedger->appended[0]['eventType'])->toBe('step.executed');
    expect($executionRegistry->started)->toHaveCount(1);
    expect($queue->dispatched)->toHaveCount(1);
    expect($queue->dispatched[0]['job']->step->stepId)->toBe('B');
});

it('suspends when the protocol executor returns a suspended outcome, without invoking StepOutcomeHandler', function (): void {
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = makeWorkerDocument($workflow);
    $definitionId = $definitionRegistry->register($document);

    [$worker, , $store, $eventLedger, , $queue] = makeWorker(StepExecutionOutcome::suspended(), $definitionRegistry);

    $context = (new WorkflowContext($definitionId))->withExecutionId('exec_1')->withWorkflowId('wf_1');

    $worker->handle(new ExecuteStepJob($step, $context));

    expect($store->saves['exec_1']['steps']['A']['status'])->toBe(StepStatus::Suspended);
    expect($eventLedger->appended[0]['eventType'])->toBe('step.suspended');
    expect($queue->dispatched)->toBeEmpty(); // StepOutcomeHandler never called, so no choreography dispatch
});

it('reloads and merges persisted state before evaluating, so a concurrently-completed sibling step is not lost', function (): void {
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $stepA = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $stepB = new Step('B', null, null, null, null, [], null, [], [], [], []);
    $stepD = new Step('D', null, null, null, null, [], null, [], [], [], [], ['A', 'B']);
    $workflow = new Workflow('wf_1', null, null, null, [], [$stepA, $stepB, $stepD], [], [], [], []);
    $document = makeWorkerDocument($workflow);
    $definitionId = $definitionRegistry->register($document);

    [$worker, , $store, , , $queue] = makeWorker(
        StepExecutionOutcome::resolved(200, [], []),
        $definitionRegistry,
    );

    // Simulate step A already completed and persisted by a concurrent worker before this
    // job (for step B) is handled.
    $store->preloaded['exec_1'] = [
        'definitionId' => $definitionId,
        'workflowId' => 'wf_1',
        'steps' => ['A' => ['statusCode' => 200, 'status' => StepStatus::Succeeded]],
        'inputs' => [],
        'components' => [],
    ];

    $context = (new WorkflowContext($definitionId))->withExecutionId('exec_1')->withWorkflowId('wf_1');

    $worker->handle(new ExecuteStepJob($stepB, $context));

    // D depends on both A and B; A came from the reloaded persisted state, B from this job.
    expect($store->saves['exec_1']['steps'])->toHaveKeys(['A', 'B']);
    expect($queue->dispatched)->toHaveCount(1);
    expect($queue->dispatched[0]['job']->step->stepId)->toBe('D');
});

it('acquires the lock using an execution-scoped key, not a definition-scoped key', function (): void {
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = makeWorkerDocument($workflow);
    $definitionId = $definitionRegistry->register($document);

    [$worker, $lockManager] = makeWorker(StepExecutionOutcome::resolved(200, [], []), $definitionRegistry);

    $context = (new WorkflowContext($definitionId))->withExecutionId('exec_42')->withWorkflowId('wf_1');

    $worker->handle(new ExecuteStepJob($step, $context));

    expect($lockManager->keysUsed[0])->toBe('execution_lock_exec_42');
});
