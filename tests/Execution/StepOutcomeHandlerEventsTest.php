<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Action\FailureEndAction;
use Alama\LaravelArazzo\Dto\Action\RetryAction;
use Alama\LaravelArazzo\Dto\Action\SuccessEndAction;
use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher;
use Alama\LaravelArazzo\Events\RunCompleted;
use Alama\LaravelArazzo\Events\RunFailed;
use Alama\LaravelArazzo\Events\StepRetried;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\ExecutionStatus;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\PendingCorrelation;
use Alama\LaravelArazzo\Execution\StepOutcomeHandler;
use Alama\LaravelArazzo\Execution\SubWorkflowInvoker;
use Alama\LaravelArazzo\Execution\SyncQueueDriver;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Resolution\SelectorEvaluator;
use Psr\Http\Message\RequestInterface;

class OutcomeEventsMockStateStore implements StateStoreInterface
{
    public array $saves = [];

    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
        $this->saves[$executionId] = $state;
    }

    public function load(string $executionId): ?array
    {
        return $this->saves[$executionId] ?? null;
    }
}

class OutcomeEventsMockEventLedger implements EventLedgerInterface
{
    public array $appended = [];

    public function append(string $executionId, string $eventType, array $payload): void
    {
        $this->appended[] = ['executionId' => $executionId, 'eventType' => $eventType, 'payload' => $payload];
    }
}

class OutcomeEventsMockExecutionRegistry implements ExecutionRegistryInterface
{
    public array $completed = [];

    public function start(string $executionId, string $definitionId, string $workflowId): void
    {
    }

    public function complete(string $executionId, ExecutionStatus $status): void
    {
        $this->completed[] = ['executionId' => $executionId, 'status' => $status];
    }
}

class OutcomeEventsMockPendingCorrelationRegistry implements PendingCorrelationRegistryInterface
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

class OutcomeEventsMockExpressionResolver implements ExpressionResolverInterface
{
    public function evaluate(\Alama\LaravelArazzo\Dto\Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed
    {
        return $expression->raw;
    }

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
    }

    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
    {
        throw new LogicException('not used');
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
        return true;
    }
}

class OutcomeEventsCollector
{
    public array $events = [];

    public function add(object $e): void
    {
        $this->events[] = $e;
    }
}

function createStepOutcomeEventsHarness(): array
{
    $dispatcher = new SimpleEventDispatcher();
    $collector = new OutcomeEventsCollector();

    $dispatcher->subscribe(StepRetried::class, fn ($e) => $collector->add($e));
    $dispatcher->subscribe(RunCompleted::class, fn ($e) => $collector->add($e));
    $dispatcher->subscribe(RunFailed::class, fn ($e) => $collector->add($e));

    $queue = new SyncQueueDriver();
    $store = new OutcomeEventsMockStateStore();
    $engine = new Engine($queue, $store, $dispatcher);
    $execRegistry = new OutcomeEventsMockExecutionRegistry();
    $ledger = new OutcomeEventsMockEventLedger();
    $correlations = new OutcomeEventsMockPendingCorrelationRegistry();
    $resolver = new OutcomeEventsMockExpressionResolver();

    $handler = new StepOutcomeHandler(
        queueDriver: $queue,
        engine: $engine,
        executionRegistry: $execRegistry,
        eventLedger: $ledger,
        pendingCorrelations: $correlations,
        expressionResolver: $resolver,
        stateStore: $store,
        invoker: Mockery::mock(SubWorkflowInvoker::class),
        selectors: Mockery::mock(SelectorEvaluator::class),
        expressions: new ExpressionEvaluator(),
        events: $dispatcher,
    );

    return [$handler, $collector];
}

it('dispatches StepRetried when RetryAction fires', function () {
    $retryAction = new RetryAction('retryOp', 0, 3, null, null, []);
    $step = new Step('step1', null, 'op1', null, null, [], null, [], [], [$retryAction], []);
    $wf = new Workflow('wf1', null, null, null, [], [$step], [], [], [], []);
    $doc = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [$wf],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );

    [$handler, $collector] = createStepOutcomeEventsHarness();

    $context = new WorkflowContext('def-1', [], [], [], 'wf1', 'exec1');
    $handler->handle($doc, $wf, $step, $context, 'exec1', false);

    expect($collector->events)->toHaveCount(1);
    expect($collector->events[0])->toBeInstanceOf(StepRetried::class);
    expect($collector->events[0]->executionId)->toBe('exec1');
    expect($collector->events[0]->workflowId)->toBe('wf1');
    expect($collector->events[0]->stepId)->toBe('step1');
    expect($collector->events[0]->attempt)->toBe(0);
    expect($collector->events[0]->lastError)->toBeNull();
    expect($collector->events[0]->at)->toBeInstanceOf(DateTimeImmutable::class);
});

it('dispatches RunCompleted on SuccessEndAction terminal', function () {
    $endAction = new SuccessEndAction('endSuccess', []);
    $step = new Step('step1', null, 'op1', null, null, [], null, [], [$endAction], [], ['token' => 'abc12345']);
    $wf = new Workflow('wf1', null, null, null, [], [$step], [], [], [], []);
    $doc = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [$wf],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );

    [$handler, $collector] = createStepOutcomeEventsHarness();

    $context = new WorkflowContext('def-1', [], [], [], 'wf1', 'exec1');
    $handler->handle($doc, $wf, $step, $context, 'exec1', true);

    expect($collector->events)->toHaveCount(1);
    expect($collector->events[0])->toBeInstanceOf(RunCompleted::class);
    expect($collector->events[0]->executionId)->toBe('exec1');
    expect($collector->events[0]->workflowId)->toBe('wf1');
    expect($collector->events[0]->outputs)->toBe(['token' => 'abc12345']);
    expect($collector->events[0]->at)->toBeInstanceOf(DateTimeImmutable::class);
});

it('dispatches RunFailed on FailureEndAction terminal', function () {
    $endAction = new FailureEndAction('endFailure', []);
    $step = new Step('step1', null, 'op1', null, null, [], null, [], [], [$endAction], []);
    $wf = new Workflow('wf1', null, null, null, [], [$step], [], [], [], []);
    $doc = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [$wf],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );

    [$handler, $collector] = createStepOutcomeEventsHarness();

    $context = new WorkflowContext('def-1', [], [], [], 'wf1', 'exec1');
    $handler->handle($doc, $wf, $step, $context, 'exec1', false);

    expect($collector->events)->toHaveCount(1);
    expect($collector->events[0])->toBeInstanceOf(RunFailed::class);
    expect($collector->events[0]->executionId)->toBe('exec1');
    expect($collector->events[0]->workflowId)->toBe('wf1');
    expect($collector->events[0]->cause)->toBeInstanceOf(RuntimeException::class);
    expect($collector->events[0]->cause->getMessage())->toBe("Workflow 'wf1' ended in failure at step 'step1'");
    expect($collector->events[0]->at)->toBeInstanceOf(DateTimeImmutable::class);
});
