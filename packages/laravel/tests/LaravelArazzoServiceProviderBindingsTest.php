<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests;

use Alama\Arazzo\Contracts\AiClientInterface;
use Alama\Arazzo\Contracts\DefinitionRegistryInterface;
use Alama\Arazzo\Contracts\EventLedgerInterface;
use Alama\Arazzo\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Contracts\HttpClientInterface;
use Alama\Arazzo\Contracts\LockManagerInterface;
use Alama\Arazzo\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Contracts\QueueDriverInterface;
use Alama\Arazzo\Contracts\StateStoreInterface;
use Alama\Arazzo\Generator\ArazzoGenerator;
use Alama\Arazzo\Generator\Clients\OpenAiClient;
use Alama\Arazzo\Laravel\Http\Psr18HttpClient;
use Alama\Arazzo\Laravel\Lock\LaravelRedisLockManager;
use Alama\Arazzo\Laravel\Persistence\DatabaseDefinitionRegistry;
use Alama\Arazzo\Laravel\Persistence\DatabaseEventLedger;
use Alama\Arazzo\Laravel\Persistence\DatabaseExecutionRegistry;
use Alama\Arazzo\Laravel\Persistence\DatabasePendingCorrelationRegistry;
use Alama\Arazzo\Laravel\Queue\LaravelQueueDriver;
use Alama\Arazzo\Laravel\State\RedisHotStateStore;
use Alama\Arazzo\Runner\Execution\AsyncApiStepExecutor;
use Alama\Arazzo\Runner\Execution\CorrelationResumer;
use Alama\Arazzo\Runner\Execution\HttpStepExecutor;
use Alama\Arazzo\Runner\Execution\StepExecutionWorker;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\StepOutcomeHandler;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
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
    app()->bind(HttpClientInterface::class, fn () => \Mockery::mock(HttpClientInterface::class));
    app()->bind(ExpressionResolverInterface::class, fn () => \Mockery::mock(ExpressionResolverInterface::class));

    expect(app(StepExecutionWorker::class))->toBeInstanceOf(StepExecutionWorker::class);
});

it('binds the queue/lock/http infra', function () {
    expect(app(HttpClientInterface::class))->toBeInstanceOf(Psr18HttpClient::class);
    expect(app(LockManagerInterface::class))->toBeInstanceOf(LaravelRedisLockManager::class);
    expect(app(QueueDriverInterface::class))->toBeInstanceOf(LaravelQueueDriver::class);
    expect(app(WorkflowEngine::class))->toBeInstanceOf(WorkflowEngine::class);
});

it('binds the async control flow classes', function () {
    expect(app(PendingCorrelationRegistryInterface::class))->toBeInstanceOf(DatabasePendingCorrelationRegistry::class);
    expect(app(StepOutcomeHandler::class))->toBeInstanceOf(StepOutcomeHandler::class);
    expect(app(HttpStepExecutor::class))->toBeInstanceOf(HttpStepExecutor::class);
    expect(app(AsyncApiStepExecutor::class))->toBeInstanceOf(AsyncApiStepExecutor::class);
    expect(app(CorrelationResumer::class))->toBeInstanceOf(CorrelationResumer::class);
    expect(app(StepExecutionWorker::class))->toBeInstanceOf(StepExecutionWorker::class);
});
