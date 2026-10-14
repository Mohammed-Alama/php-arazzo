<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests;

use Alama\Arazzo\Cli\Generator\ArazzoGenerator;
use Alama\Arazzo\Cli\Generator\Clients\OpenAiClient;
use Alama\Arazzo\Contracts\Interfaces\AiClientInterface;
use Alama\Arazzo\Contracts\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Contracts\Interfaces\StepProtocolExecutorInterface;
use Alama\Arazzo\Document\Document;
use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Laravel\Http\Psr18HttpClient;
use Alama\Arazzo\Laravel\Lock\LaravelRedisLockManager;
use Alama\Arazzo\Laravel\Persistence\DatabaseDefinitionRegistry;
use Alama\Arazzo\Laravel\Persistence\DatabaseEventLedger;
use Alama\Arazzo\Laravel\Persistence\DatabaseExecutionRegistry;
use Alama\Arazzo\Laravel\Persistence\DatabasePendingCorrelationRegistry;
use Alama\Arazzo\Laravel\Queue\LaravelQueueDriver;
use Alama\Arazzo\Laravel\State\RedisHotStateStore;
use Alama\Arazzo\Protocol\Http\Protocol\AsyncApiStepExecutor;
use Alama\Arazzo\Protocol\Http\Protocol\HttpStepExecutor;
use Alama\Arazzo\Runner\Async\CorrelationResumer;
use Alama\Arazzo\Runner\Async\StepExecutionWorker;
use Alama\Arazzo\Runner\Async\StepOutcomeHandler;
use Alama\Arazzo\Runner\AsyncExecutionGraph;
use Alama\Arazzo\Runner\RunnerFacade;
use Alama\Arazzo\Runner\RunnerFacadeInterface;
use Alama\Arazzo\Runner\Sync\StepExecutor;
use Alama\Arazzo\Runner\Sync\WorkflowEngine;
use Alama\Arazzo\Runner\Sync\WorkflowExecutor;
use Alama\Arazzo\Runtime\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\StateStoreInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

it('binds psr interfaces to guzzle', function () {
    expect(app(ClientInterface::class))->toBeInstanceOf(Client::class);
    expect(app(RequestFactoryInterface::class))->toBeInstanceOf(HttpFactory::class);
    expect(app(StreamFactoryInterface::class))->toBeInstanceOf(HttpFactory::class);
});

it('binds AiClientInterface', function () {
    expect(app(AiClientInterface::class))->toBeInstanceOf(OpenAiClient::class);
});

it('binds ArazzoGenerator', function () {
    expect(app(ArazzoGenerator::class))->toBeInstanceOf(ArazzoGenerator::class);
});

it('binds WorkflowExecutor and exposes the sync step executor through the graph', function () {
    expect(app(WorkflowExecutor::class))->toBeInstanceOf(WorkflowExecutor::class);
    expect(app(AsyncExecutionGraph::class)->stepExecutor())->toBeInstanceOf(StepExecutor::class);
});

it('binds the persistence interfaces to their Laravel implementations', function () {
    expect(app(StateStoreInterface::class))->toBeInstanceOf(RedisHotStateStore::class);
    expect(app(EventLedgerInterface::class))->toBeInstanceOf(DatabaseEventLedger::class);
    expect(app(DefinitionRegistryInterface::class))->toBeInstanceOf(DatabaseDefinitionRegistry::class);
    expect(app(ExecutionRegistryInterface::class))->toBeInstanceOf(DatabaseExecutionRegistry::class);
});

it('exposes the worker through the async graph (no container alias)', function () {
    app()->bind(LockManagerInterface::class, fn () => \Mockery::mock(LockManagerInterface::class));
    app()->bind(HttpClientInterface::class, fn () => \Mockery::mock(HttpClientInterface::class));

    expect(app(AsyncExecutionGraph::class)->worker())->toBeInstanceOf(StepExecutionWorker::class);
});

it('binds the queue/lock/http infra', function () {
    expect(app(HttpClientInterface::class))->toBeInstanceOf(Psr18HttpClient::class);
    expect(app(LockManagerInterface::class))->toBeInstanceOf(LaravelRedisLockManager::class);
    expect(app(QueueDriverInterface::class))->toBeInstanceOf(LaravelQueueDriver::class);
    expect(app(WorkflowEngine::class))->toBeInstanceOf(WorkflowEngine::class);
});

it('exposes the async control flow nodes through the graph', function () {
    expect(app(PendingCorrelationRegistryInterface::class))->toBeInstanceOf(DatabasePendingCorrelationRegistry::class);

    $graph = app(AsyncExecutionGraph::class);

    expect($graph->outcomeHandler())->toBeInstanceOf(StepOutcomeHandler::class)
        ->and($graph->resumer())->toBeInstanceOf(CorrelationResumer::class)
        ->and($graph->worker())->toBeInstanceOf(StepExecutionWorker::class);
});

it('assembles every protocol executor into the graph', function () {
    // The protocol executors stay runner-internal: the seam rule forbids
    // packages/laravel/src from naming Runner\Protocol\*, so they are reached
    // through the graph's public handle rather than container bindings.
    $executors = app(AsyncExecutionGraph::class)->protocolExecutors();

    expect($executors)->toHaveCount(3);

    foreach ($executors as $executor) {
        expect($executor)->toBeInstanceOf(StepProtocolExecutorInterface::class);
    }

    expect(array_map(static fn (object $e): string => $e::class, $executors))
        ->toContain(HttpStepExecutor::class, AsyncApiStepExecutor::class);
});

it('binds the entry-point facade interfaces to their self-contained facades', function () {
    expect(app(ExpressionEngineInterface::class))->toBeInstanceOf(ExpressionEngine::class);
    expect(app(EvaluationEngineInterface::class))->toBeInstanceOf(EvaluationEngine::class);
    expect(app(DocumentInterface::class))->toBeInstanceOf(Document::class);
    expect(app(RunnerFacadeInterface::class))->toBeInstanceOf(RunnerFacade::class);
});
