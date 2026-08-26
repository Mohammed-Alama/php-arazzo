<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\EventLedgerInterface;
use Alama\Arazzo\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Contracts\LockManagerInterface;
use Alama\Arazzo\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Contracts\StateStoreInterface;
use Alama\Arazzo\Contracts\StepProtocolExecutorInterface;
use Alama\Arazzo\Events\CorrelationPending;
use Alama\Arazzo\Events\StepExecuted as EventStepExecuted;
use Alama\Arazzo\Events\StepFailed;
use Alama\Arazzo\Events\StepStarted;
use Alama\Arazzo\Execution\ExecutionStatus;
use Alama\Arazzo\Execution\InMemoryDefinitionRegistry;
use Alama\Arazzo\Execution\RunControlFlow;
use Alama\Arazzo\Execution\RunPersistence;
use Alama\Arazzo\Execution\StepExecutionOutcome;
use Alama\Arazzo\Execution\StepExecutionWorker;
use Alama\Arazzo\Execution\StepOutcomeHandler;
use Alama\Arazzo\Execution\SubWorkflowInvoker;
use Alama\Arazzo\Execution\SyncQueueDriver;
use Alama\Arazzo\Execution\WorkflowEngine;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Expression\SelectorEvaluator;
use Alama\Arazzo\Jobs\ExecuteStepJob;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\State\PendingCorrelation;
use Alama\Arazzo\State\WorkflowContext;
use Alama\Arazzo\Support\Events\Dispatcher\SimpleEventDispatcher;

class WorkerEventsMockLockManager implements LockManagerInterface
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

class WorkerEventsMockStateStore implements StateStoreInterface
{
    public array $saves = [];

    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
        $this->saves[$executionId] = $state;
    }

    public function load(string $executionId): ?array
    {
        return null;
    }
}

class WorkerEventsMockEventLedger implements EventLedgerInterface
{
    public array $appended = [];

    public function append(string $executionId, string $eventType, array $payload): void
    {
        $this->appended[] = ['executionId' => $executionId, 'eventType' => $eventType, 'payload' => $payload];
    }
}

class WorkerEventsMockExecutionRegistry implements ExecutionRegistryInterface
{
    public function start(string $executionId, string $definitionId, string $workflowId): void {}

    public function complete(string $executionId, ExecutionStatus $status): void {}
}

class WorkerEventsMockExpressionResolver implements ExpressionResolverInterface
{
    public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed
    {
        return $expression->raw;
    }

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void {}

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
        return true;
    }
}

class WorkerEventsFakeExecutor implements StepProtocolExecutorInterface
{
    public function __construct(private ?StepExecutionOutcome $outcome = null, private ?Throwable $toThrow = null) {}

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return true;
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        if ($this->toThrow !== null) {
            throw $this->toThrow;
        }

        return $this->outcome ?? StepExecutionOutcome::resolved(200, [], []);
    }
}

class WorkerEventsMockPendingCorrelationRegistry implements PendingCorrelationRegistryInterface
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
}

class WorkerEventCollector
{
    public array $events = [];

    public function add(object $e): void
    {
        $this->events[] = $e;
    }
}

function createWorkerEventsHarness(?StepExecutionOutcome $outcome = null, ?Throwable $toThrow = null): array
{
    $dispatcher = new SimpleEventDispatcher();
    $collector = new WorkerEventCollector();

    $dispatcher->subscribe(StepStarted::class, function ($e) use ($collector) {
        $collector->add($e);
    });
    $dispatcher->subscribe(EventStepExecuted::class, function ($e) use ($collector) {
        $collector->add($e);
    });
    $dispatcher->subscribe(CorrelationPending::class, function ($e) use ($collector) {
        $collector->add($e);
    });
    $dispatcher->subscribe(StepFailed::class, function ($e) use ($collector) {
        $collector->add($e);
    });

    $defRegistry = new InMemoryDefinitionRegistry();
    $lockManager = new WorkerEventsMockLockManager();
    $store = new WorkerEventsMockStateStore();
    $ledger = new WorkerEventsMockEventLedger();
    $execRegistry = new WorkerEventsMockExecutionRegistry();
    $resolver = new WorkerEventsMockExpressionResolver();
    $queue = new SyncQueueDriver();
    $outcomeHandler = new StepOutcomeHandler(
        new RunPersistence($store, $ledger, $execRegistry),
        new RunControlFlow(new WorkflowEngine($resolver), $queue),
        pendingCorrelations: new WorkerEventsMockPendingCorrelationRegistry(),
        invoker: Mockery::mock(SubWorkflowInvoker::class),
        selectors: Mockery::mock(SelectorEvaluator::class),
        expressions: Mockery::mock(ExpressionEvaluator::class),
    );

    $executor = new WorkerEventsFakeExecutor($outcome, $toThrow);

    $worker = new StepExecutionWorker(
        new RunPersistence($store, $ledger, $execRegistry),
        $lockManager,
        $defRegistry,
        $resolver,
        [$executor],
        new RunControlFlow(new WorkflowEngine($resolver), $queue, events: $dispatcher),
        stateTtlSeconds: 86400,
    );

    return [$worker, $defRegistry, $collector];
}

it('dispatches StepStarted then StepExecuted on happy path', function () {
    $step = new Step('step1', null, 'op1', null, null, [], null, [], [], [], []);
    $wf = new Workflow('wf1', null, null, null, [], [$step], [], [], [], []);
    $doc = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [$wf],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );

    [$worker, $defRegistry, $collector] = createWorkerEventsHarness(
        StepExecutionOutcome::resolved(200, ['outKey' => 'val'], []),
    );
    $defId = $defRegistry->register($doc);

    $ctx = (new WorkflowContext($defId, [], [], [], 'wf1', 'exec1'));
    $job = new ExecuteStepJob($step, $ctx);

    $worker->handle($job);

    $dispatched = $collector->events;
    expect($dispatched)->toHaveCount(2);
    expect($dispatched[0])->toBeInstanceOf(StepStarted::class);
    expect($dispatched[0]->executionId)->toBe('exec1');
    expect($dispatched[0]->workflowId)->toBe('wf1');
    expect($dispatched[0]->stepId)->toBe('step1');
    expect($dispatched[0]->attempt)->toBe(1);

    expect($dispatched[1])->toBeInstanceOf(EventStepExecuted::class);
    expect($dispatched[1]->executionId)->toBe('exec1');
    expect($dispatched[1]->workflowId)->toBe('wf1');
    expect($dispatched[1]->stepId)->toBe('step1');
    expect($dispatched[1]->statusCode)->toBe(200);
    expect($dispatched[1]->outputs)->toBe(['outKey' => 'val']);
    expect($dispatched[1]->criteriaMet)->toBeTrue();
});

it('dispatches StepStarted then CorrelationPending on action receive suspend', function () {
    $step = new Step(
        stepId: 'recvStep',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
        dependsOn: [],
        action: 'receive',
        channelPath: 'notifications/channel',
        correlationId: new Expression('$inputs.orderId'),
    );
    $wf = new Workflow('wf1', null, null, null, [], [$step], [], [], [], []);
    $doc = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [$wf],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );

    [$worker, $defRegistry, $collector] = createWorkerEventsHarness(
        StepExecutionOutcome::suspended(),
    );
    $defId = $defRegistry->register($doc);

    $ctx = (new WorkflowContext($defId, ['orderId' => 'ord-123'], [], [], 'wf1', 'exec1'));
    $job = new ExecuteStepJob($step, $ctx);

    $worker->handle($job);

    $dispatched = $collector->events;
    expect($dispatched)->toHaveCount(2);
    expect($dispatched[0])->toBeInstanceOf(StepStarted::class);
    expect($dispatched[1])->toBeInstanceOf(CorrelationPending::class);
    expect($dispatched[1]->executionId)->toBe('exec1');
    expect($dispatched[1]->workflowId)->toBe('wf1');
    expect($dispatched[1]->stepId)->toBe('recvStep');
    expect($dispatched[1]->correlationId)->toBe('$inputs.orderId');
    expect($dispatched[1]->channelPath)->toBe('notifications/channel');
});

it('dispatches StepStarted then StepFailed when executor throws', function () {
    $step = new Step('step1', null, 'op1', null, null, [], null, [], [], [], []);
    $wf = new Workflow('wf1', null, null, null, [], [$step], [], [], [], []);
    $doc = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [$wf],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );

    $exception = new RuntimeException('Executor connection failed');
    [$worker, $defRegistry, $collector] = createWorkerEventsHarness(
        toThrow: $exception,
    );
    $defId = $defRegistry->register($doc);

    $ctx = (new WorkflowContext($defId, [], [], [], 'wf1', 'exec1'));
    $job = new ExecuteStepJob($step, $ctx);

    expect(fn () => $worker->handle($job))->toThrow(RuntimeException::class, 'Executor connection failed');

    $dispatched = $collector->events;
    expect($dispatched)->toHaveCount(2);
    expect($dispatched[0])->toBeInstanceOf(StepStarted::class);
    expect($dispatched[1])->toBeInstanceOf(StepFailed::class);
    expect($dispatched[1]->executionId)->toBe('exec1');
    expect($dispatched[1]->workflowId)->toBe('wf1');
    expect($dispatched[1]->stepId)->toBe('step1');
    expect($dispatched[1]->cause)->toBe($exception);
});
