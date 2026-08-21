<?php

declare(strict_types=1);

namespace Tests\Laravel\Jobs;

use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Laravel\Queue\Jobs\RunExecuteStepJob;
use Alama\Arazzo\Runner\Jobs\ExecuteStepJob;
use Alama\Arazzo\Runner\StepExecutionWorker;
use Alama\Arazzo\Runner\WorkflowContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

class RecordingStepExecutionWorker extends StepExecutionWorker
{
    /** @var list<ExecuteStepJob> */
    public array $handled = [];

    public function __construct()
    {
    }

    public function handle(ExecuteStepJob $job): void
    {
        $this->handled[] = $job;
    }
}

it('round-trips ExecuteStepJob through a real Laravel queue connection and reaches StepExecutionWorker::handle()', function (): void {
    config(['queue.default' => 'sync']);

    $recorder = new RecordingStepExecutionWorker();
    $this->app->instance(StepExecutionWorker::class, $recorder);

    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $context = (new WorkflowContext('def_1'))->withExecutionId('exec_1');
    $innerJob = new ExecuteStepJob($step, $context);

    $wrapper = new RunExecuteStepJob($innerJob);

    expect($wrapper->definitionId)->toBe('def_1');
    expect($wrapper->workflowId)->toBeNull();
    expect($wrapper->executionId)->toBe('exec_1');

    Queue::connection('sync')->push($wrapper);

    expect($recorder->handled)->toHaveCount(1);
    expect($recorder->handled[0]->step->stepId)->toBe('A');
    expect($recorder->handled[0]->context->getExecutionId())->toBe('exec_1');
    // A genuinely different instance -- confirms it went through real serialize/unserialize,
    // not just an in-memory closure call.
    expect($recorder->handled[0])->not->toBe($innerJob);
});

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Enum\SourceType;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Runner\Contracts\DefinitionRegistryInterface;
use Alama\Arazzo\Runner\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Contracts\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Contracts\StateStoreInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('injects idempotency key natively during job execution independently of StepExecutor', function (): void {
    // 1. Setup minimal step & workflow context
    $step = new Step('step-1', null, 'op', null, null, [], null, [], [], [], []);
    $executionId = 'exec-idempotency-test-' . bin2hex(random_bytes(8));
    $context = (new WorkflowContext('def-1'))->withWorkflowId('wf-1')->withExecutionId($executionId);
    $workflow = new Workflow('wf-1', 'WF 1', null, [], [], [], [], [], [], []);
    $document = new ArazzoDocument('1.0', new Info('t', null, null, '1'), [new SourceDescription('src', 'http://api.example.com', SourceType::Openapi)], [$workflow], new Components([], [], [], []), []);

    $capturedRequest = null;
    $openApiMock = \Mockery::mock(OpenApiExecutorInterface::class);
    $openApiMock->shouldReceive('execute')->once()->andReturnUsing(function ($source, $op, $payload, $interceptor) use (&$capturedRequest) {
        $request = new Request('POST', 'https://api.example.com/charges', [], '{"amount":100}');
        if ($interceptor) {
            $request = $interceptor($request);
        }
        $capturedRequest = $request;

        return new Response(201, [], '{}');
    });
    app()->instance(OpenApiExecutorInterface::class, $openApiMock);

    $resolver = \Mockery::mock(ExpressionResolverInterface::class);
    $resolver->shouldReceive('extractOutputs')->andReturn([]);
    $resolver->shouldReceive('evaluateSuccessCriteria')->andReturn(true);
    app()->instance(ExpressionResolverInterface::class, $resolver);

    $registry = \Mockery::mock(DefinitionRegistryInterface::class);
    $registry->shouldReceive('get')->with('def-1')->andReturn($document);
    app()->instance(DefinitionRegistryInterface::class, $registry);

    app()->instance(StateStoreInterface::class, new class() implements StateStoreInterface
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
    });
    app()->forgetInstance(StepExecutionWorker::class);

    // Enable the idempotency feature in config so the binding passes it to the Job's dependencies
    config(['arazzo.idempotency.enabled' => true]);

    $job = new RunExecuteStepJob(new ExecuteStepJob($step, $context));

    $worker = app(StepExecutionWorker::class);
    $job->handle($worker);

    // 5. Assert the header is present and valid
    expect($capturedRequest)->not->toBeNull();
    expect($capturedRequest->getHeaderLine('Idempotency-Key'))->toMatch('/^[0-9a-f]{64}$/');
});
