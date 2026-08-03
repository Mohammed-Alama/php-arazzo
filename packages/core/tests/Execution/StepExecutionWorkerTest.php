<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;
use Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher;
use Alama\LaravelArazzo\Events\Listener\LedgerAppendingListener;
use Alama\Arazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\Arazzo\Execution\Contracts\EventLedgerInterface;
use Alama\Arazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Execution\Contracts\LockManagerInterface;
use Alama\Arazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Execution\Contracts\StateStoreInterface;
use Alama\Arazzo\Execution\Contracts\StepProtocolExecutorInterface;
use Alama\Arazzo\Execution\Engine;
use Alama\Arazzo\Execution\ExecutionStatus;
use Alama\Arazzo\Execution\ExpressionEvaluator;
use Alama\Arazzo\Execution\InMemoryDefinitionRegistry;
use Alama\Arazzo\Execution\Jobs\ExecuteStepJob;
use Alama\Arazzo\Execution\PendingCorrelation;
use Alama\Arazzo\Execution\StepExecutionOutcome;
use Alama\Arazzo\Execution\StepExecutionWorker;
use Alama\Arazzo\Execution\StepOutcomeHandler;
use Alama\Arazzo\Execution\StepStatus;
use Alama\Arazzo\Execution\SubWorkflowInvoker;
use Alama\Arazzo\Execution\SyncQueueDriver;
use Alama\Arazzo\Execution\WorkflowContext;
use Alama\Arazzo\Resolution\SelectorEvaluator;
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
    public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed
    {
        return $expression->raw;
    }

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
    }

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
function makeWorker(StepExecutionOutcome $outcome, DefinitionRegistryInterface $definitionRegistry): array
{
    $lockManager = new WorkerMockLockManager();
    $store = new WorkerMockStateStore();
    $eventLedger = new WorkerMockEventLedger();
    $executionRegistry = new WorkerMockExecutionRegistry();
    $resolver = new WorkerMockExpressionResolver();
    $queue = new SyncQueueDriver();
    $dispatcher = new SimpleEventDispatcher();
    LedgerAppendingListener::registerAll($dispatcher, $eventLedger);

    $engine = new Engine($queue, $store, $dispatcher);
    $outcomeHandler = new StepOutcomeHandler(
        $queue, $engine, $executionRegistry, $eventLedger,
        new WorkerMockPendingCorrelationRegistry(), $resolver, $store,
        \Mockery::mock(SubWorkflowInvoker::class),
        \Mockery::mock(SelectorEvaluator::class),
        \Mockery::mock(ExpressionEvaluator::class),
    );

    $worker = new StepExecutionWorker(
        $lockManager, $store, $definitionRegistry, $eventLedger, $executionRegistry, $resolver,
        [new WorkerFakeProtocolExecutor($outcome)], $outcomeHandler, null, 86400, $dispatcher,
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
    expect(array_column($eventLedger->appended, 'eventType'))->toContain('step.executed');
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
    expect(array_column($eventLedger->appended, 'eventType'))->toContain('step.suspended');
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

it('resolves a diamond fan-in exactly once: B and C both complete from the same stale context, D dispatches exactly once with A+B+C all present', function (): void {
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $stepA = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $stepB = new Step('B', null, null, null, null, [], null, [], [], [], [], ['A']);
    $stepC = new Step('C', null, null, null, null, [], null, [], [], [], [], ['A']);
    $stepD = new Step('D', null, null, null, null, [], null, [], [], [], [], ['B', 'C']);
    $workflow = new Workflow('wf_1', null, null, null, [], [$stepA, $stepB, $stepC, $stepD], [], [], [], []);
    $document = makeWorkerDocument($workflow);
    $definitionId = $definitionRegistry->register($document);

    [$worker, , $store, , , $queue] = makeWorker(StepExecutionOutcome::resolved(200, [], []), $definitionRegistry);

    // A already completed and persisted by an earlier job.
    $store->preloaded['exec_1'] = [
        'definitionId' => $definitionId,
        'workflowId' => 'wf_1',
        'steps' => ['A' => ['statusCode' => 200, 'status' => StepStatus::Succeeded]],
        'inputs' => [],
        'components' => [],
    ];

    // B and C were both dispatched right after A completed, so both jobs carry the exact
    // same A-only context snapshot -- this is the classic diamond/fan-in lost-update race.
    $staleContext = (new WorkflowContext($definitionId))->withExecutionId('exec_1')->withWorkflowId('wf_1');

    $worker->handle(new ExecuteStepJob($stepB, $staleContext));

    // WorkerMockStateStore keeps save()/load() as two separate arrays for test clarity
    // elsewhere in this file -- bridge them here to simulate B's write becoming visible to
    // C's subsequent load(), exactly like a real shared StateStore would.
    $store->preloaded['exec_1'] = $store->saves['exec_1'];

    $worker->handle(new ExecuteStepJob($stepC, $staleContext));

    $dDispatches = array_values(array_filter($queue->dispatched, fn ($d) => $d['job']->step->stepId === 'D'));
    expect($dDispatches)->toHaveCount(1);
    expect($store->saves['exec_1']['steps'])->toHaveKeys(['A', 'B', 'C']);
});
