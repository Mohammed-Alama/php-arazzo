<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo;

use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Execution\ArazzoExpressionResolver;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\StepExecutor;
use Alama\LaravelArazzo\Execution\WorkflowExecutor;
use Alama\LaravelArazzo\Generator\ArazzoGenerator;
use Alama\LaravelArazzo\Generator\Clients\OpenAiClient;
use Alama\LaravelArazzo\Generator\Contracts\AiClientInterface;
use Alama\LaravelArazzo\Http\Controllers\ArazzoApiController;
use Alama\LaravelArazzo\Parser\Parser;
use Alama\LaravelArazzo\Resolution\DefaultSourceResolver;
use Alama\LaravelArazzo\Resolution\Fetchers\CachedFetcher;
use Alama\LaravelArazzo\Resolution\Fetchers\HttpFetcher;
use Alama\LaravelArazzo\Resolution\Fetchers\LocalFetcher;
use Alama\LaravelArazzo\Resolution\Parsers\ArazzoSourceParser;
use Alama\LaravelArazzo\Resolution\Parsers\OpenApiSourceParser;
use Alama\LaravelArazzo\Resolution\SourceResolver;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\Facades\Route;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\StepExecutionWorker;
use Alama\LaravelArazzo\Laravel\DatabaseDefinitionRegistry;
use Alama\LaravelArazzo\Laravel\DatabaseEventLedger;
use Alama\LaravelArazzo\Laravel\DatabaseExecutionRegistry;
use Alama\LaravelArazzo\Laravel\RedisHotStateStore;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

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
            ])
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        // PSR HTTP Bindings
        $this->app->bindIf(ClientInterface::class, Client::class);
        $this->app->bindIf(RequestFactoryInterface::class, HttpFactory::class);
        $this->app->bindIf(StreamFactoryInterface::class, HttpFactory::class);

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

        // Persistence
        $this->app->singleton(StateStoreInterface::class, function ($app) {
            return new RedisHotStateStore(
                $app->make(RedisFactory::class),
                defaultTtlSeconds: (int) config('arazzo.hot_state_ttl', 86400),
            );
        });

        $this->app->singleton(EventLedgerInterface::class, function ($app) {
            return new DatabaseEventLedger(
                $app->make('db')->connection(),
                config('arazzo.events_table', 'arazzo_events'),
                $app->bound(\Psr\Log\LoggerInterface::class) ? $app->make(\Psr\Log\LoggerInterface::class) : null,
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

        $this->app->singleton(StepExecutionWorker::class, function ($app) {
            return new StepExecutionWorker(
                $app->make(\Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface::class),
                $app->make(StateStoreInterface::class),
                $app->make(Engine::class),
                $app->make(ClientInterface::class),
                $app->make(ExpressionResolverInterface::class),
                $app->make(DefinitionRegistryInterface::class),
                $app->make(EventLedgerInterface::class),
                $app->make(ExecutionRegistryInterface::class),
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

        Route::prefix('api/arazzo')
            ->middleware('api')
            ->group(function () {
                Route::get('/endpoints', [ArazzoApiController::class, 'endpoints']);
                Route::post('/generate', [ArazzoApiController::class, 'generate']);
            });
    }
}
