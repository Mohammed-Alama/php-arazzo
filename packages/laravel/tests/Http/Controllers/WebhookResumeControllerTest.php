<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests\Http\Controllers;

use Alama\Arazzo\Dto\Enum\Format;
use Alama\Arazzo\Dto\Enum\SourceType;
use Alama\Arazzo\Dto\RawDocument;
use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Laravel\Queue\Jobs\RunResumeCorrelationJob;
use Alama\Arazzo\Loader\SymfonyYamlDecoder;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Runner\Contracts\DefinitionRegistryInterface;
use Alama\Arazzo\Runner\Contracts\LockManagerInterface;
use Alama\Arazzo\Runner\Contracts\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\Contracts\QueueDriverInterface;
use Alama\Arazzo\Runner\Contracts\StateStoreInterface;
use Alama\Arazzo\Runner\CorrelationResumer;
use Alama\Arazzo\Runner\Dto\OpenApiPayload;
use Alama\Arazzo\Runner\Jobs\ExecuteStepJob;
use Alama\Arazzo\Runner\Normalizer\NormalizedOpenApiOperation;
use Alama\Arazzo\Runner\PendingCorrelation;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Runner\Resolver\ResolvedOperation;
use Alama\Arazzo\Runner\StepExecutionWorker;
use Alama\Arazzo\Runner\WorkflowContext;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Psr\Http\Message\ResponseInterface;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

class WebhookControllerMockPendingCorrelations implements PendingCorrelationRegistryInterface
{
    public ?PendingCorrelation $toReturn = null;

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void
    {
    }

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        return $this->toReturn;
    }

    public function consume(string $correlationId): void
    {
    }

    public function existsForExecution(string $executionId): bool
    {
        return false;
    }
}

it('returns 404 and dispatches nothing when the correlation is unknown', function (): void {
    Queue::fake();
    $this->app->instance(PendingCorrelationRegistryInterface::class, new WebhookControllerMockPendingCorrelations());

    postJson('/api/arazzo/webhooks/unknown-corr', ['rideId' => 'r_1'])
        ->assertStatus(404);

    Queue::assertNothingPushed();
});

it('returns 202 and dispatches a ResumeCorrelationJob when the correlation is found', function (): void {
    Queue::fake();
    $fake = new WebhookControllerMockPendingCorrelations();
    $fake->toReturn = new PendingCorrelation('corr_1', 'exec_1', 'wait-for-ride', 'channels/rides/created');
    $this->app->instance(PendingCorrelationRegistryInterface::class, $fake);

    postJson('/api/arazzo/webhooks/corr_1', ['rideId' => 'r_1'])
        ->assertStatus(202);

    Queue::assertPushed(RunResumeCorrelationJob::class, function (RunResumeCorrelationJob $pushed) {
        return $pushed->inner->correlationId === 'corr_1' && $pushed->inner->response === ['rideId' => 'r_1'];
    });
});

it('runs a full HTTP -> AsyncAPI suspend/resume saga end to end via the fixture document', function (): void {
    config(['queue.default' => 'sync']);

    $this->app->instance(StateStoreInterface::class, new class() implements StateStoreInterface
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

    $this->app->forgetInstance(StepExecutionWorker::class);
    $this->app->forgetInstance(CorrelationResumer::class);
    $this->app->instance(LockManagerInterface::class, new class() implements LockManagerInterface
    {
        public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
        {
            return $callback();
        }
    });

    $this->app->instance(OpenApiExecutorInterface::class, new class() implements OpenApiExecutorInterface
    {
        public function execute(ResolvedOperation $operation, OpenApiPayload $payload, ?callable $requestInterceptor = null): ResponseInterface
        {
            return new Response(201, [], json_encode(['rideId' => 'r_1']));
        }
    });

    $opResolver = \Mockery::mock(OpenApiOperationResolver::class);
    $opResolver->shouldReceive('resolve')->andReturn(
        new ResolvedOperation(
            new SourceDescription('src', 'openapi.yaml', SourceType::Openapi),
            new NormalizedOpenApiOperation('/paths/~1rides/post', 'post', 'http://api.example.com', [], [], [], [], [], []),
            new OpenApi([]),
            [],
            new Operation([]),
        ),
    );
    $this->app->instance(OpenApiOperationResolver::class, $opResolver);

    $rawYaml = file_get_contents(__DIR__ . '/../../fixtures/parser/arazzo-1.0-webhook-saga.yaml');
    $decoded = (new SymfonyYamlDecoder())->decode($rawYaml);
    $document = (new Parser())->parse(new RawDocument(
        (array) $decoded,
        'file://arazzo-1.0-webhook-saga.yaml',
        Format::Yaml,
    ));

    $definitionRegistry = app(DefinitionRegistryInterface::class);
    $definitionId = $definitionRegistry->register($document);

    $workflow = $document->workflows[0];
    $createRide = $workflow->steps[0];

    $context = new WorkflowContext($definitionId, [], [], [], 'ride-saga', 'exec_saga_1');

    app(QueueDriverInterface::class)->dispatch(new ExecuteStepJob($createRide, $context));

    $pendingCorrelation = DB::table('arazzo_pending_correlations')->where('execution_id', 'exec_saga_1')->first();
    expect($pendingCorrelation)->not->toBeNull();
    expect($pendingCorrelation->correlation_id)->toBe('r_1');
    expect($pendingCorrelation->step_id)->toBe('wait-for-ride-status');

    postJson('/api/arazzo/webhooks/r_1', ['status' => 'completed'])
        ->assertStatus(202);

    expect(DB::table('arazzo_pending_correlations')->where('correlation_id', 'r_1')->exists())->toBeFalse();

    $executionRow = DB::table('arazzo_executions')->where('id', 'exec_saga_1')->first();
    expect($executionRow->status)->toBe('succeeded');
});
