<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo;

use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Execution\ArazzoExpressionResolver;
use Alama\LaravelArazzo\Execution\AsyncApiStepExecutor;
use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\CorrelationResumer;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\HttpStepExecutor;
use Alama\LaravelArazzo\Execution\StepExecutionWorker;
use Alama\LaravelArazzo\Execution\StepExecutor;
use Alama\LaravelArazzo\Execution\StepOutcomeHandler;
use Alama\LaravelArazzo\Execution\WorkflowExecutor;
use Alama\LaravelArazzo\Generator\ArazzoGenerator;
use Alama\LaravelArazzo\Generator\Clients\OpenAiClient;
use Alama\LaravelArazzo\Generator\Contracts\AiClientInterface;
use Alama\LaravelArazzo\Http\Controllers\ArazzoApiController;
use Alama\LaravelArazzo\Laravel\Http\Controllers\WebhookResumeController;
use Alama\LaravelArazzo\Laravel\DatabaseDefinitionRegistry;
use Alama\LaravelArazzo\Laravel\DatabaseEventLedger;
use Alama\LaravelArazzo\Laravel\DatabaseExecutionRegistry;
use Alama\LaravelArazzo\Laravel\DatabasePendingCorrelationRegistry;
use Alama\LaravelArazzo\Laravel\LaravelQueueDriver;
use Alama\LaravelArazzo\Laravel\LaravelRedisLockManager;
use Alama\LaravelArazzo\Laravel\Psr18HttpClient;
use Alama\LaravelArazzo\Laravel\RedisHotStateStore;
use Alama\LaravelArazzo\Parser\Parser;
use Alama\LaravelArazzo\Resolution\DefaultSourceResolver;
use Alama\LaravelArazzo\Resolution\Fetchers\CachedFetcher;
use Alama\LaravelArazzo\Resolution\Fetchers\HttpFetcher;
use Alama\LaravelArazzo\Resolution\Fetchers\LocalFetcher;
use Alama\LaravelArazzo\Resolution\Parsers\ArazzoSourceParser;
use Alama\LaravelArazzo\Resolution\Parsers\AsyncApiSourceParser;
use Alama\LaravelArazzo\Resolution\Parsers\OpenApiSourceParser;
use Alama\LaravelArazzo\Resolution\SourceResolver;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Facades\Route;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class LaravelArazzoServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-arazzo')
            ->hasConfigFile('arazzo')
            ->hasMigrations([
                'create_arazzo_definitions_table',
                'create_arazzo_executions_table',
                'create_arazzo_events_table',
                'add_status_to_arazzo_executions_table',
                'create_arazzo_pending_correlations_table',
            ])
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        // PSR HTTP Bindings
        $this->app->bindIf(ClientInterface::class, Client::class);
        $this->app->bindIf(RequestFactoryInterface::class, HttpFactory::class);
        $this->app->bindIf(StreamFactoryInterface::class, HttpFactory::class);

        $this->app->singleton(HttpClientInterface::class, function ($app) {
            return new Psr18HttpClient($app->make(ClientInterface::class));
        });

        // Core Resolver
        $this->app->singleton(SourceResolver::class, function () {
            return new DefaultSourceResolver(
                fetchers: [
                    'http' => new CachedFetcher(new HttpFetcher(), 3600),
                    'https' => new CachedFetcher(new HttpFetcher(), 3600),
                    'file' => new LocalFetcher(),
                ],
                parsers: [
                    SourceType::Openapi->value => new OpenApiSourceParser(),
                    SourceType::Arazzo->value => new ArazzoSourceParser(new Parser()),
                    SourceType::Asyncapi->value => new AsyncApiSourceParser(),
                ],
            );
        });

        // AI Generator
        $this->app->singleton(AiClientInterface::class, function ($app) {
            return new OpenAiClient(
                $app->make(ClientInterface::class),
                $app->make(RequestFactoryInterface::class),
                $app->make(StreamFactoryInterface::class),
                config('arazzo.openai.api_key', ''),
                config('arazzo.openai.model', 'gpt-4o'),
            );
        });

        $this->app->singleton(ArazzoGenerator::class, function ($app) {
            return new ArazzoGenerator($app->make(AiClientInterface::class));
        });

        // Workflow Execution
        $this->app->singleton(ExpressionResolverInterface::class, function ($app) {
            return new ArazzoExpressionResolver(
                $app->make(SourceResolver::class),
                $app->make(RequestFactoryInterface::class),
                new ExpressionEvaluator(),
            );
        });

        $this->app->singleton(StepExecutor::class, function ($app) {
            return new StepExecutor(
                $app->make(ClientInterface::class),
                $app->make(ExpressionResolverInterface::class),
            );
        });

        $this->app->singleton(WorkflowExecutor::class, function ($app) {
            return new WorkflowExecutor($app->make(StepExecutor::class));
        });

        // Persistence (doc 02)
        $this->app->singleton(StateStoreInterface::class, function ($app) {
            return new RedisHotStateStore(
                $app->make(RedisFactory::class),
                defaultTtlSeconds: (int) config('arazzo.state_ttl', 86400),
            );
        });

        $this->app->singleton(EventLedgerInterface::class, function ($app) {
            return new DatabaseEventLedger(
                $app->make('db')->connection(),
                config('arazzo.events_table', 'arazzo_events'),
                $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null,
            );
        });

        $this->app->singleton(DefinitionRegistryInterface::class, function ($app) {
            return new DatabaseDefinitionRegistry(
                $app->make('db')->connection(),
                new Parser(),
                config('arazzo.definitions_table', 'arazzo_definitions'),
            );
        });

        $this->app->singleton(ExecutionRegistryInterface::class, function ($app) {
            return new DatabaseExecutionRegistry(
                $app->make('db')->connection(),
                config('arazzo.executions_table', 'arazzo_executions'),
            );
        });

        // Queue / lock infra (doc 03 -- doc 02's plan explicitly left these bindings to this item)
        $this->app->singleton(LockManagerInterface::class, LaravelRedisLockManager::class);
        $this->app->singleton(QueueDriverInterface::class, LaravelQueueDriver::class);

        $this->app->singleton(Engine::class, function ($app) {
            return new Engine(
                new DependencyAnalyzer(),
                $app->make(QueueDriverInterface::class),
                $app->make(StateStoreInterface::class),
            );
        });

        // Async control flow (doc 03)
        $this->app->singleton(PendingCorrelationRegistryInterface::class, function ($app) {
            return new DatabasePendingCorrelationRegistry(
                $app->make('db')->connection(),
                config('arazzo.pending_correlations_table', 'arazzo_pending_correlations'),
            );
        });

        $this->app->singleton(StepOutcomeHandler::class, function ($app) {
            return new StepOutcomeHandler(
                $app->make(QueueDriverInterface::class),
                $app->make(Engine::class),
                new DependencyAnalyzer(),
                $app->make(ExecutionRegistryInterface::class),
                $app->make(EventLedgerInterface::class),
                $app->make(PendingCorrelationRegistryInterface::class),
                $app->make(ExpressionResolverInterface::class),
                $app->make(StateStoreInterface::class),
                (int) config('arazzo.retry_ceiling', 10),
                (int) config('arazzo.state_ttl', 86400),
            );
        });

        $this->app->singleton(HttpStepExecutor::class, function ($app) {
            return new HttpStepExecutor(
                $app->make(HttpClientInterface::class),
                $app->make(ExpressionResolverInterface::class),
            );
        });

        $this->app->singleton(AsyncApiStepExecutor::class, function ($app) {
            return new AsyncApiStepExecutor(
                $app->make(PendingCorrelationRegistryInterface::class),
                new ExpressionEvaluator(),
                $app->make(HttpClientInterface::class),
                $app->make(ExpressionResolverInterface::class),
            );
        });

        $this->app->singleton(CorrelationResumer::class, function ($app) {
            return new CorrelationResumer(
                $app->make(PendingCorrelationRegistryInterface::class),
                $app->make(StateStoreInterface::class),
                $app->make(DefinitionRegistryInterface::class),
                $app->make(ExpressionResolverInterface::class),
                $app->make(StepOutcomeHandler::class),
                $app->make(EventLedgerInterface::class),
                $app->make(LockManagerInterface::class),
            );
        });

        $this->app->singleton(StepExecutionWorker::class, function ($app) {
            return new StepExecutionWorker(
                $app->make(LockManagerInterface::class),
                $app->make(StateStoreInterface::class),
                $app->make(DefinitionRegistryInterface::class),
                $app->make(EventLedgerInterface::class),
                $app->make(ExecutionRegistryInterface::class),
                $app->make(ExpressionResolverInterface::class),
                [
                    $app->make(HttpStepExecutor::class),
                    $app->make(AsyncApiStepExecutor::class),
                ],
                $app->make(StepOutcomeHandler::class),
            );
        });
    }

    public function packageBooted(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'arazzo');

        Route::get('/arazzo-builder', function () {
            /** @var view-string $view */
            $view = 'arazzo::arazzo';

            return view($view);
        })->middleware('web');

        Route::prefix(config('arazzo.webhook_prefix', 'api/arazzo'))
            ->middleware('api')
            ->group(function () {
                Route::get('/endpoints', [ArazzoApiController::class, 'endpoints']);
                Route::post('/generate', [ArazzoApiController::class, 'generate']);
                Route::post('/webhooks/{correlationId}', [WebhookResumeController::class, 'resume']);
            });
    }
}
