<?php

declare(strict_types=1);

namespace Tests\Laravel\Jobs;

use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Document\NormalizedOpenApiOperation;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Document\Validator\Data\ValidationResult;
use Alama\Arazzo\Laravel\Queue\Jobs\RunExecuteStepJob;
use Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Async\ExecuteStepJob;
use Alama\Arazzo\Runner\AsyncExecutionGraph;
use Alama\Arazzo\Runner\OperationExecutorRegistry;
use Alama\Arazzo\Runtime\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\StateStoreInterface;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationHandle;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Queue;

class RecordingRunStepPlugin implements OperationExecutorPluginInterface
{
    /** @var list<Step> */
    public array $executed = [];

    public function name(): string
    {
        return 'run-job-fake';
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
        $this->executed[] = $step;

        return StepExecutionOutcome::resolved(200, ['from' => $step->stepId], [], inputs: []);
    }
}

function runJobStateStore(): StateStoreInterface
{
    return new class() implements StateStoreInterface
    {
        /** @var array<string, array<string, mixed>> */
        private array $store = [];

        public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
        {
            $this->store[$executionId] = $state;
        }

        public function load(string $executionId): ?array
        {
            return $this->store[$executionId] ?? null;
        }

        public function delete(string $executionId): void
        {
            unset($this->store[$executionId]);
        }
    };
}

it('round-trips ExecuteStepJob through a real Laravel queue connection and reaches the graph worker', function (): void {
    config(['queue.default' => 'sync']);

    $workflow = new Workflow('wf-1', 'WF 1', null, [], [], [
        $inStep = new Step('A', null, new StepTarget(), new StepFlow(), new StepIo()),
    ], [], [], [], []);
    $document = new ArazzoDocument('1.0.0', new Info('t', null, null, '1'), [], [$workflow], new Components([], [], [], []), []);

    $registered = \Mockery::mock(DefinitionRegistryInterface::class);
    $registered->shouldReceive('get')->with('def_1')->andReturn($document);
    app()->instance(DefinitionRegistryInterface::class, $registered);

    app()->instance(StateStoreInterface::class, runJobStateStore());

    // The worker executes steps through the executor registry the graph was
    // assembled with — a recording plugin proves the step actually ran.
    $recorder = new RecordingRunStepPlugin();
    $executors = new OperationExecutorRegistry();
    $executors->register($recorder);
    app()->instance(OperationExecutorRegistry::class, $executors);

    app()->forgetInstance(AsyncExecutionGraph::class);

    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf-1')->withExecutionId('exec_1');
    $innerJob = new ExecuteStepJob($inStep, $context);

    $wrapper = new RunExecuteStepJob($innerJob);

    expect($wrapper->definitionId)->toBe('def_1');
    expect($wrapper->workflowId)->toBe('wf-1');
    expect($wrapper->executionId)->toBe('exec_1');

    Queue::connection('sync')->push($wrapper);

    expect($recorder->executed)->toHaveCount(1);
    expect($recorder->executed[0]->stepId)->toBe('A');
    // A genuinely different instance -- confirms it went through real serialize/unserialize,
    // not just an in-memory closure call.
    expect($recorder->executed[0])->not->toBe($inStep);
});

it('injects idempotency key natively during job execution independently of StepExecutor', function (): void {
    // 1. Setup minimal step & workflow context
    $step = StepFactory::http('step-1', null, new StepFlow(), new StepIo(), operationId: 'op');
    $executionId = 'exec-idempotency-test-'.bin2hex(random_bytes(8));
    $context = (new WorkflowContext('def-1'))->withWorkflowId('wf-1')->withExecutionId($executionId);
    $workflow = new Workflow('wf-1', 'WF 1', null, [], [], [], [], [], [], []);
    $document = new ArazzoDocument('1.0', new Info('t', null, null, '1'), [new SourceDescription('src', 'http://api.example.com', SourceType::Openapi)], [$workflow], new Components([], [], [], []), []);

    $capturedRequest = null;
    $openApiMock = \Mockery::mock(OpenApiExecutorInterface::class);
    $openApiMock->shouldReceive('execute')->once()->andReturnUsing(function ($op, $payload, $interceptor) use (&$capturedRequest) {
        $request = new Request('POST', 'https://api.example.com/charges', [], '{"amount":100}');
        if ($interceptor) {
            $request = $interceptor($request);
        }
        $capturedRequest = $request;

        return new Response(201, [], '{}');
    });
    app()->instance(OpenApiExecutorInterface::class, $openApiMock);

    $documents = \Mockery::mock(DocumentInterface::class);
    $operations = \Mockery::mock(OpenApiOperationResolver::class);
    app()->instance(OpenApiOperationResolver::class, $operations);
    $operations->shouldReceive('resolve')->andReturn(new OpenApiOperationHandle(
        new ResolvedOperation(
            new SourceDescription('src', 'http://api.example.com', SourceType::Openapi),
            new NormalizedOpenApiOperation('/charges', 'post', 'http://api.example.com', [], [], [], [], [], []),
        ),
        new OpenApi([]),
        new Operation([]),
    ));
    $documents->shouldReceive('preflight')->andReturnUsing(fn (ArazzoDocument $d) => new ValidationResult($d, [], []));
    $documents->shouldReceive('preflightInputs')->andReturnUsing(fn (ArazzoDocument $d) => new ValidationResult($d, [], []));
    app()->instance(DocumentInterface::class, $documents);

    $registry = \Mockery::mock(DefinitionRegistryInterface::class);
    $registry->shouldReceive('get')->with('def-1')->andReturn($document);
    app()->instance(DefinitionRegistryInterface::class, $registry);

    app()->instance(StateStoreInterface::class, runJobStateStore());
    app()->forgetInstance(AsyncExecutionGraph::class);

    // Enable the idempotency feature in config so the binding passes it to the Job's dependencies
    config(['arazzo.idempotency.enabled' => true]);

    $job = new RunExecuteStepJob(new ExecuteStepJob($step, $context));

    $job->handle(app(AsyncExecutionGraph::class));

    // 5. Assert the header is present and valid
    expect($capturedRequest)->not->toBeNull();
    expect($capturedRequest->getHeaderLine('Idempotency-Key'))->toMatch('/^[0-9a-f]{64}$/');
});
