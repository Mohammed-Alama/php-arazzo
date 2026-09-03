<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Bindings;

use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Document\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Document\Validator\PreflightValidator;
use Alama\Arazzo\Expression\Evaluation\CriteriaEvaluator;
use Alama\Arazzo\Expression\Evaluation\ExpressionResolver;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Expression\SelectorEvaluator;
use Alama\Arazzo\Laravel\Support\ConfigValue;
use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\Execution\CorrelationResumer;
use Alama\Arazzo\Runner\Execution\Data\RunControlFlow;
use Alama\Arazzo\Runner\Execution\Data\RunPersistence;
use Alama\Arazzo\Runner\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Runner\Execution\IdempotencyKeyInjector;
use Alama\Arazzo\Runner\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Execution\ResponseSchemaValidator;
use Alama\Arazzo\Runner\Execution\StepExecutionWorker;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\StepOutcomeHandler;
use Alama\Arazzo\Runner\Execution\StepOutputExtractor;
use Alama\Arazzo\Runner\Execution\SubWorkflowInvoker;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use Alama\Arazzo\Runner\Infrastructure\Interfaces\HttpClientInterface;
use Alama\Arazzo\Runner\Protocol\AsyncApiStepExecutor;
use Alama\Arazzo\Runner\Protocol\HttpStepExecutor;
use Alama\Arazzo\Runner\Protocol\SubWorkflowStepExecutor;
use Alama\Arazzo\Runner\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\StateStoreInterface;
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

            return new ExpressionResolver(
                $evaluator,
                new StepOutputExtractor($operationResolver, $evaluator),
                new CriteriaEvaluator($evaluator),
                new ResponseSchemaValidator($operationResolver),
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
