<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel;

use Alama\Arazzo\Generator\ArazzoGenerator;
use Alama\Arazzo\Generator\Clients\OpenAiClient;
use Alama\Arazzo\Generator\Contracts\AiClientInterface;
use Alama\Arazzo\Laravel\Http\Controllers\ArazzoApiController;
use Alama\Arazzo\Laravel\Http\Controllers\WebhookResumeController;
use Alama\Arazzo\Laravel\Http\Psr18HttpClient;
use Alama\Arazzo\Laravel\Lock\LaravelRedisLockManager;
use Alama\Arazzo\Laravel\Persistence\DatabaseDefinitionRegistry;
use Alama\Arazzo\Laravel\Persistence\DatabaseEventLedger;
use Alama\Arazzo\Laravel\Persistence\DatabaseExecutionRegistry;
use Alama\Arazzo\Laravel\Persistence\DatabasePendingCorrelationRegistry;
use Alama\Arazzo\Laravel\Queue\LaravelQueueDriver;
use Alama\Arazzo\Laravel\State\RedisHotStateStore;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\Fetchers\CachedFetcher;
use Alama\Arazzo\Resolver\Fetchers\HttpFetcher;
use Alama\Arazzo\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Resolver\SourceRegistry;
use Alama\Arazzo\Resolver\SourceResolver;
use Alama\Arazzo\Runner\Context\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\Context\Contracts\StateStoreInterface;
use Alama\Arazzo\Runner\Evaluation\ArazzoCriteriaEvaluator;
use Alama\Arazzo\Runner\Evaluation\ArazzoExpressionResolver;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Runner\Evaluation\SelectorEvaluator;
use Alama\Arazzo\Runner\Evaluation\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Runner\Evaluation\Xpath\XpathEvaluator;
use Alama\Arazzo\Runner\Execution\ArazzoOutputExtractor;
use Alama\Arazzo\Runner\Execution\ArazzoSchemaValidator;
use Alama\Arazzo\Runner\Execution\AsyncApiStepExecutor;
use Alama\Arazzo\Runner\Execution\Contracts\DefinitionRegistryInterface;
use Alama\Arazzo\Runner\Execution\Contracts\EventLedgerInterface;
use Alama\Arazzo\Runner\Execution\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\Execution\Contracts\HttpClientInterface;
use Alama\Arazzo\Runner\Execution\Contracts\LockManagerInterface;
use Alama\Arazzo\Runner\Execution\Contracts\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Execution\Contracts\QueueDriverInterface;
use Alama\Arazzo\Runner\Execution\CorrelationResumer;
use Alama\Arazzo\Runner\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Runner\Execution\Engine;
use Alama\Arazzo\Runner\Execution\HttpStepExecutor;
use Alama\Arazzo\Runner\Execution\IdempotencyKeyInjector;
use Alama\Arazzo\Runner\Execution\OpenApiDocumentLoader;
use Alama\Arazzo\Runner\Execution\StepExecutionWorker;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\StepOutcomeHandler;
use Alama\Arazzo\Runner\Execution\SubWorkflowInvoker;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use Alama\Arazzo\Runner\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Support\Events\Listener\LedgerAppendingListener;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Route;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class LaravelArazzoServiceProvider extends PackageServiceProvider
{
    public function register(): void
    {
        if (class_exists(AliasLoader::class)) {
            $loader = AliasLoader::getInstance();
            $loader->alias('Alama\LaravelArazzo\LaravelArazzoServiceProvider', self::class);
            $loader->alias('Alama\LaravelArazzo\Http\Controllers\ArazzoApiController', ArazzoApiController::class);
            $loader->alias('Alama\LaravelArazzo\Laravel\Http\Controllers\WebhookResumeController', WebhookResumeController::class);
        } else {
            // Fallback for non-facade environments (e.g. testing)
            class_alias(self::class, 'Alama\LaravelArazzo\LaravelArazzoServiceProvider');
            class_alias(ArazzoApiController::class, 'Alama\LaravelArazzo\Http\Controllers\ArazzoApiController');
            class_alias(WebhookResumeController::class, 'Alama\LaravelArazzo\Laravel\Http\Controllers\WebhookResumeController');
        }

        parent::register();
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-arazzo')
            ->hasConfigFile('arazzo')
            ->hasMigrations([
                'create_arazzo_definitions_table',
                'create_arazzo_executions_table',
                'create_arazzo_events_table',
                'update_arazzo_executions_table_add_status',
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

        // Event Dispatcher
        $this->app->singleton(SimpleEventDispatcher::class, function ($app) {
            $dispatcher = new SimpleEventDispatcher();

            if ($app->bound(EventLedgerInterface::class)) {
                LedgerAppendingListener::registerAll(
                    $dispatcher,
                    $app->make(EventLedgerInterface::class),
                );
            }

            return $dispatcher;
        });

        $this->app->bindIf(
            EventDispatcherInterface::class,
            fn ($app) => $app->make(SimpleEventDispatcher::class),
        );

        // Core Resolver
        $this->app->singleton(SourceResolver::class, function ($app) {
            $defaultResolver = new DefaultSourceResolver(
                fetchers: [
                    'http' => new CachedFetcher(new HttpFetcher($app->make(ClientInterface::class), $app->make(RequestFactoryInterface::class)), $app->make(CacheInterface::class), 3600),
                    'https' => new CachedFetcher(new HttpFetcher($app->make(ClientInterface::class), $app->make(RequestFactoryInterface::class)), $app->make(CacheInterface::class), 3600),
                    'file' => new LocalFetcher(),
                ],
            );

            return new SourceRegistry($defaultResolver);
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

        $this->app->singleton(OpenApiDocumentLoader::class, function ($app) {
            return new OpenApiDocumentLoader($app->make(SourceResolver::class));
        });

        $this->app->singleton(OpenApiOperationResolver::class, function ($app) {
            return new OpenApiOperationResolver(
                $app->make(OpenApiDocumentLoader::class),
                $app->make(OpenApiVersionDetector::class),
                $app->make(OpenApi30Normalizer::class),
                $app->make(OpenApi31Normalizer::class),
            );
        });

        // Workflow Execution
        $this->app->singleton(ExpressionResolverInterface::class, function ($app) {
            $evaluator = new ExpressionEvaluator();
            $operationResolver = $app->make(OpenApiOperationResolver::class);

            $outputExtractor = new ArazzoOutputExtractor($operationResolver, $evaluator);
            $criteriaEvaluator = new ArazzoCriteriaEvaluator($evaluator);
            $schemaValidator = new ArazzoSchemaValidator($operationResolver);

            return new ArazzoExpressionResolver(
                $evaluator,
                $outputExtractor,
                $criteriaEvaluator,
                $schemaValidator,
            );
        });

        $this->app->singleton(IdempotencyKeyInjector::class, function ($app) {
            return new IdempotencyKeyInjector(
                enabledDefault: (bool) config('arazzo.idempotency.enabled', false),
                headerDefault: (string) config('arazzo.idempotency.header', 'Idempotency-Key'),
            );
        });

        $this->app->singleton(OpenApiExecutorInterface::class, function ($app) {
            return new DefaultOpenApiExecutor(
                $app->make(ClientInterface::class),
                $app->make(RequestFactoryInterface::class),
                $app->make(LoggerInterface::class),
            );
        });

        $this->app->singleton(StepExecutor::class, function ($app) {
            return new StepExecutor(
                $app->make(OpenApiExecutorInterface::class),
                $app->make(ExpressionResolverInterface::class),
                $app->make(OpenApiOperationResolver::class),
                (bool) config('arazzo.strict_schema_validation', false),
                $app->make(IdempotencyKeyInjector::class),
            );
        });

        $this->app->singleton(WorkflowExecutor::class, function ($app) {
            return new WorkflowExecutor(
                $app->make(StepExecutor::class),
                workflowEngine: $app->make(WorkflowEngine::class),
            );
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
                $app->make(QueueDriverInterface::class),
                $app->make(StateStoreInterface::class),
            );
        });

        $this->app->singleton(WorkflowEngine::class, function ($app) {
            return new WorkflowEngine(
                $app->make(ExpressionResolverInterface::class),
                (int) config('arazzo.retry_ceiling', 10),
            );
        });

        // Async control flow (doc 03)
        $this->app->singleton(PendingCorrelationRegistryInterface::class, function ($app) {
            return new DatabasePendingCorrelationRegistry(
                $app->make('db')->connection(),
                config('arazzo.pending_correlations_table', 'arazzo_pending_correlations'),
            );
        });

        $this->app->singleton(XpathEvaluator::class, function ($app) {
            return new DomXpathEvaluator();
        });

        $this->app->singleton(SelectorEvaluator::class, function ($app) {
            return new SelectorEvaluator(
                new DomXpathEvaluator(),
                new ExpressionEvaluator(),
            );
        });

        $this->app->singleton(SubWorkflowInvoker::class, function ($app) {
            return new SubWorkflowInvoker(
                $app->make(DefinitionRegistryInterface::class),
                $app->make(WorkflowExecutor::class),
                new ExpressionEvaluator(),
                $app->make(SelectorEvaluator::class),
            );
        });

        $this->app->singleton(StepOutcomeHandler::class, function ($app) {
            return new StepOutcomeHandler(
                $app->make(QueueDriverInterface::class),
                $app->make(Engine::class),
                $app->make(ExecutionRegistryInterface::class),
                $app->make(EventLedgerInterface::class),
                $app->make(PendingCorrelationRegistryInterface::class),
                $app->make(ExpressionResolverInterface::class),
                $app->make(StateStoreInterface::class),
                $app->make(SubWorkflowInvoker::class),
                $app->make(SelectorEvaluator::class),
                new ExpressionEvaluator(),
                (int) config('arazzo.retry_ceiling', 10),
                (int) config('arazzo.state_ttl', 86400),
            );
        });

        $this->app->singleton(HttpStepExecutor::class, function ($app) {
            return new HttpStepExecutor(
                $app->make(OpenApiExecutorInterface::class),
                $app->make(ExpressionResolverInterface::class),
                $app->make(OpenApiOperationResolver::class),
                (bool) config('arazzo.strict_schema_validation', false),
                $app->make(IdempotencyKeyInjector::class),
            );
        });

        $this->app->singleton(AsyncApiStepExecutor::class, function ($app) {
            $httpFactory = new HttpFactory();

            return new AsyncApiStepExecutor(
                $app->make(PendingCorrelationRegistryInterface::class),
                new ExpressionEvaluator(),
                $app->make(HttpClientInterface::class),
                $httpFactory,
                $httpFactory,
                $httpFactory,
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
                workflowEngine: $app->make(WorkflowEngine::class),
                queueDriver: $app->make(QueueDriverInterface::class),
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
