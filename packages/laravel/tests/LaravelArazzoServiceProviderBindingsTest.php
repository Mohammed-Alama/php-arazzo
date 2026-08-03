<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests;

use Alama\Arazzo\Execution\AsyncApiStepExecutor;
use Alama\Arazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\Arazzo\Execution\Contracts\EventLedgerInterface;
use Alama\Arazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Execution\Contracts\HttpClientInterface;
use Alama\Arazzo\Execution\Contracts\LockManagerInterface;
use Alama\Arazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Execution\Contracts\QueueDriverInterface;
use Alama\Arazzo\Execution\Contracts\StateStoreInterface;
use Alama\Arazzo\Execution\CorrelationResumer;
use Alama\Arazzo\Execution\Engine;
use Alama\Arazzo\Execution\HttpStepExecutor;
use Alama\Arazzo\Execution\StepExecutionWorker;
use Alama\Arazzo\Execution\StepExecutor;
use Alama\Arazzo\Execution\StepOutcomeHandler;
use Alama\Arazzo\Execution\WorkflowExecutor;
use Alama\Arazzo\Generator\ArazzoGenerator;
use Alama\Arazzo\Generator\Clients\OpenAiClient;
use Alama\Arazzo\Generator\Contracts\AiClientInterface;
use Alama\Arazzo\Laravel\Http\Psr18HttpClient;
use Alama\Arazzo\Laravel\Lock\LaravelRedisLockManager;
use Alama\Arazzo\Laravel\Persistence\DatabaseDefinitionRegistry;
use Alama\Arazzo\Laravel\Persistence\DatabaseEventLedger;
use Alama\Arazzo\Laravel\Persistence\DatabaseExecutionRegistry;
use Alama\Arazzo\Laravel\Persistence\DatabasePendingCorrelationRegistry;
use Alama\Arazzo\Laravel\Queue\LaravelQueueDriver;
use Alama\Arazzo\Laravel\State\RedisHotStateStore;
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
