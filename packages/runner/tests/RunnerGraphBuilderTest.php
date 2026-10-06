<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\PendingCorrelation;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Engine\Exceptions\ExecutionException;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Runner\Async\ExecuteStepJob;
use Alama\Arazzo\Runner\Async\StepExecutionWorker;
use Alama\Arazzo\Runner\AsyncGraphSeams;
use Alama\Arazzo\Runner\Execution\InMemoryDefinitionRegistry;
use Alama\Arazzo\Runner\OperationExecutorRegistry;
use Alama\Arazzo\Runner\RunnerGraphBuilder;
use Alama\Arazzo\Runtime\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\StateStoreInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class BuilderRecordingPlugin implements OperationExecutorPluginInterface
{
    /** @var list<string> */
    public array $executed = [];

    public function name(): string
    {
        return 'builder-fake';
    }

    public function priority(): int
    {
        return 100;
    }

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return true;
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        $this->executed[] = $step->stepId;

        return StepExecutionOutcome::resolved(200, ['from' => $step->stepId], [], inputs: []);
    }
}

function builderSeams(DefinitionRegistryInterface $definitions, OperationExecutorRegistry $executors): AsyncGraphSeams
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
        definitionRegistry: $definitions,
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
        executorRegistry: $executors,
    );
}

function builderDocument(): ArazzoDocument
{
    $workflow = new Workflow('builder_wf', 'Builder WF', null, [], [], [
        new Step('A', null, new StepTarget(), new StepFlow(), new StepIo()),
    ], [], [], [], []);

    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Builder', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [$workflow],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

it('builds a queue worker from worker-only seams without protocol value seams', function (): void {
    $definitions = new InMemoryDefinitionRegistry();
    $definitionId = $definitions->register(builderDocument());

    $executors = new OperationExecutorRegistry();
    $plugin = new BuilderRecordingPlugin();
    $executors->register($plugin);

    $worker = (new RunnerGraphBuilder(evaluationEngine: new EvaluationEngine(expression: new ExpressionEngine())))
        ->buildWorker(builderSeams($definitions, $executors));

    expect($worker)->toBeInstanceOf(StepExecutionWorker::class);

    $context = (new WorkflowContext($definitionId))
        ->withWorkflowId('builder_wf')
        ->withExecutionId('builder_exec_1');
    $worker->handle(new ExecuteStepJob(builderDocument()->workflows[0]->steps[0], $context));

    expect($plugin->executed)->toBe(['A']);
});

it('refuses buildAsync without an OperationRuntime (worker-only hosts must use buildWorker)', function (): void {
    $definitions = new InMemoryDefinitionRegistry();
    $definitions->register(builderDocument());

    $builder = new RunnerGraphBuilder(evaluationEngine: new EvaluationEngine(expression: new ExpressionEngine()));

    expect(fn () => $builder->buildAsync(builderSeams($definitions, new OperationExecutorRegistry())))
        ->toThrow(ExecutionException::class);
});
