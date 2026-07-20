<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests;

use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\AsyncApiStepExecutor;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\CorrelationResumer;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\HttpStepExecutor;
use Alama\LaravelArazzo\Execution\StepExecutionWorker;
use Alama\LaravelArazzo\Execution\StepExecutor;
use Alama\LaravelArazzo\Execution\StepOutcomeHandler;
use Alama\LaravelArazzo\Execution\WorkflowExecutor;
use Alama\LaravelArazzo\Generator\ArazzoGenerator;
use Alama\LaravelArazzo\Generator\Clients\OpenAiClient;
use Alama\LaravelArazzo\Generator\Contracts\AiClientInterface;
use Alama\LaravelArazzo\Laravel\DatabaseDefinitionRegistry;
use Alama\LaravelArazzo\Laravel\DatabaseEventLedger;
use Alama\LaravelArazzo\Laravel\DatabaseExecutionRegistry;
use Alama\LaravelArazzo\Laravel\DatabasePendingCorrelationRegistry;
use Alama\LaravelArazzo\Laravel\LaravelQueueDriver;
use Alama\LaravelArazzo\Laravel\LaravelRedisLockManager;
use Alama\LaravelArazzo\Laravel\Psr18HttpClient;
use Alama\LaravelArazzo\Laravel\RedisHotStateStore;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

uses(TestCase::class);

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

it('binds WorkflowExecutor and StepExecutor', function () {
    expect(app(WorkflowExecutor::class))->toBeInstanceOf(WorkflowExecutor::class);
    expect(app(StepExecutor::class))->toBeInstanceOf(StepExecutor::class);
});

it('binds the persistence interfaces to their Laravel implementations', function () {
    expect(app(StateStoreInterface::class))->toBeInstanceOf(RedisHotStateStore::class);
    expect(app(EventLedgerInterface::class))->toBeInstanceOf(DatabaseEventLedger::class);
    expect(app(DefinitionRegistryInterface::class))->toBeInstanceOf(DatabaseDefinitionRegistry::class);
    expect(app(ExecutionRegistryInterface::class))->toBeInstanceOf(DatabaseExecutionRegistry::class);
});

it('binds StepExecutionWorker', function () {
    app()->bind(LockManagerInterface::class, fn () => \Mockery::mock(LockManagerInterface::class));
    app()->bind(Engine::class, fn () => \Mockery::mock(Engine::class));
    app()->bind(HttpClientInterface::class, fn () => \Mockery::mock(HttpClientInterface::class));
    app()->bind(ExpressionResolverInterface::class, fn () => \Mockery::mock(ExpressionResolverInterface::class));

    expect(app(StepExecutionWorker::class))->toBeInstanceOf(StepExecutionWorker::class);
});

it('binds the queue/lock/http infra', function () {
    expect(app(HttpClientInterface::class))->toBeInstanceOf(Psr18HttpClient::class);
    expect(app(LockManagerInterface::class))->toBeInstanceOf(LaravelRedisLockManager::class);
    expect(app(QueueDriverInterface::class))->toBeInstanceOf(LaravelQueueDriver::class);
    expect(app(Engine::class))->toBeInstanceOf(Engine::class);
});

it('binds the async control flow classes', function () {
    expect(app(PendingCorrelationRegistryInterface::class))->toBeInstanceOf(DatabasePendingCorrelationRegistry::class);
    expect(app(StepOutcomeHandler::class))->toBeInstanceOf(StepOutcomeHandler::class);
    expect(app(HttpStepExecutor::class))->toBeInstanceOf(HttpStepExecutor::class);
    expect(app(AsyncApiStepExecutor::class))->toBeInstanceOf(AsyncApiStepExecutor::class);
    expect(app(CorrelationResumer::class))->toBeInstanceOf(CorrelationResumer::class);
    expect(app(StepExecutionWorker::class))->toBeInstanceOf(StepExecutionWorker::class);
});
