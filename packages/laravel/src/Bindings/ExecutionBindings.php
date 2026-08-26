<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Bindings;

use Alama\Arazzo\Contracts\DefinitionRegistryInterface;
use Alama\Arazzo\Contracts\EventLedgerInterface;
use Alama\Arazzo\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Contracts\HttpClientInterface;
use Alama\Arazzo\Contracts\LockManagerInterface;
use Alama\Arazzo\Contracts\OpenApiExecutorInterface;
use Alama\Arazzo\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Contracts\QueueDriverInterface;
use Alama\Arazzo\Contracts\StateStoreInterface;
use Alama\Arazzo\Evaluation\ArazzoCriteriaEvaluator;
use Alama\Arazzo\Evaluation\ArazzoExpressionResolver;
use Alama\Arazzo\Execution\ArazzoOutputExtractor;
use Alama\Arazzo\Execution\ArazzoSchemaValidator;
use Alama\Arazzo\Execution\CorrelationResumer;
use Alama\Arazzo\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Execution\IdempotencyKeyInjector;
use Alama\Arazzo\Execution\RunControlFlow;
use Alama\Arazzo\Execution\RunPersistence;
use Alama\Arazzo\Execution\StepExecutionWorker;
use Alama\Arazzo\Execution\StepExecutor;
use Alama\Arazzo\Execution\StepOutcomeHandler;
use Alama\Arazzo\Execution\SubWorkflowInvoker;
use Alama\Arazzo\Execution\WorkflowEngine;
use Alama\Arazzo\Execution\WorkflowExecutor;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Expression\SelectorEvaluator;
use Alama\Arazzo\Laravel\Support\ConfigValue;
use Alama\Arazzo\Protocol\AsyncApiStepExecutor;
use Alama\Arazzo\Protocol\HttpStepExecutor;
use Alama\Arazzo\Protocol\SubWorkflowStepExecutor;
use Alama\Arazzo\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Validator\PreflightValidator;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Contracts\Container\Container;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;

/** Execution pipeline: engine, executors, outcome handling, resumption. */
final class ExecutionBindings
{
    public static function register(Container $app): void
    {
        $app->singleton(ExpressionResolverInterface::class, function (Container $app) {
            $evaluator = new ExpressionEvaluator();
            $operationResolver = $app->make(OpenApiOperationResolver::class);

            return new ArazzoExpressionResolver(
                $evaluator,
                new ArazzoOutputExtractor($operationResolver, $evaluator),
                new ArazzoCriteriaEvaluator($evaluator),
                new ArazzoSchemaValidator($operationResolver),
            );
        });

        $app->singleton(IdempotencyKeyInjector::class, function (Container $app) {
            return new IdempotencyKeyInjector(
                enabledDefault: ConfigValue::bool(config('arazzo.idempotency.enabled', false), false),
                headerDefault: ConfigValue::string(config('arazzo.idempotency.header', 'Idempotency-Key'), 'Idempotency-Key'),
            );
        });

        $app->singleton(OpenApiExecutorInterface::class, function (Container $app) {
            return new DefaultOpenApiExecutor(
                $app->make(ClientInterface::class),
                $app->make(RequestFactoryInterface::class),
                $app->make(LoggerInterface::class),
            );
        });

        $app->singleton(StepExecutor::class, function (Container $app) {
            return new StepExecutor(
                $app->make(OpenApiExecutorInterface::class),
                $app->make(ExpressionResolverInterface::class),
                $app->make(OpenApiOperationResolver::class),
                ConfigValue::bool(config('arazzo.strict_schema_validation', false), false),
                $app->make(IdempotencyKeyInjector::class),
            );
        });

        $app->singleton(WorkflowEngine::class, function (Container $app) {
            return new WorkflowEngine(
                $app->make(ExpressionResolverInterface::class),
                ConfigValue::int(config('arazzo.retry_ceiling', 10), 10),
                ConfigValue::float(config('arazzo.retry_backoff_multiplier', 1.0), 1.0),
            );
        });

        $app->singleton(WorkflowExecutor::class, function (Container $app) {
            return new WorkflowExecutor(
                $app->make(StepExecutor::class),
                workflowEngine: $app->make(WorkflowEngine::class),
                preflight: $app->make(PreflightValidator::class),
            );
        });

        $app->singleton(SubWorkflowInvoker::class, function (Container $app) {
            return new SubWorkflowInvoker(
                $app->make(DefinitionRegistryInterface::class),
                $app->make(WorkflowExecutor::class),
                new ExpressionEvaluator(),
                $app->make(SelectorEvaluator::class),
            );
        });

        $app->singleton(StepOutcomeHandler::class, function (Container $app) {
            return new StepOutcomeHandler(
                new RunPersistence(
                    $app->make(StateStoreInterface::class),
                    $app->make(EventLedgerInterface::class),
                    $app->make(ExecutionRegistryInterface::class),
                ),
                new RunControlFlow(
                    workflowEngine: $app->make(WorkflowEngine::class),
                    queueDriver: $app->make(QueueDriverInterface::class),
                ),
                pendingCorrelations: $app->make(PendingCorrelationRegistryInterface::class),
                invoker: $app->make(SubWorkflowInvoker::class),
                selectors: $app->make(SelectorEvaluator::class),
                expressions: new ExpressionEvaluator(),
                stateTtlSeconds: ConfigValue::int(config('arazzo.state_ttl', 86400), 86400),
            );
        });

        $app->singleton(HttpStepExecutor::class, function (Container $app) {
            return new HttpStepExecutor(
                $app->make(OpenApiExecutorInterface::class),
                $app->make(ExpressionResolverInterface::class),
                $app->make(OpenApiOperationResolver::class),
                ConfigValue::bool(config('arazzo.strict_schema_validation', false), false),
                $app->make(IdempotencyKeyInjector::class),
            );
        });

        $app->singleton(AsyncApiStepExecutor::class, function (Container $app) {
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

        $app->singleton(CorrelationResumer::class, function (Container $app) {
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

        $app->singleton(StepExecutionWorker::class, function (Container $app) {
            return new StepExecutionWorker(
                new RunPersistence(
                    $app->make(StateStoreInterface::class),
                    $app->make(EventLedgerInterface::class),
                    $app->make(ExecutionRegistryInterface::class),
                ),
                $app->make(LockManagerInterface::class),
                $app->make(DefinitionRegistryInterface::class),
                $app->make(ExpressionResolverInterface::class),
                [
                    $app->make(SubWorkflowStepExecutor::class),
                    $app->make(HttpStepExecutor::class),
                    $app->make(AsyncApiStepExecutor::class),
                ],
                new RunControlFlow(
                    workflowEngine: $app->make(WorkflowEngine::class),
                    queueDriver: $app->make(QueueDriverInterface::class),
                    preflight: $app->make(PreflightValidator::class),
                ),
            );
        });
    }
}
