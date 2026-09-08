<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Support;

use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\AsyncExecutionGraph;
use Alama\Arazzo\Runner\AsyncGraphSeams;
use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Infrastructure\Interfaces\HttpClientInterface;
use Alama\Arazzo\Runner\RunnerGraphBuilderInterface;
use Alama\Arazzo\Runner\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\StateStoreInterface;
use Illuminate\Contracts\Container\Container;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;

/** Builds AsyncGraphSeams from the current container state and calls the runner builder.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class AsyncGraphResolver
{
    public static function resolve(Container $app): AsyncExecutionGraph
    {
        return $app->make(RunnerGraphBuilderInterface::class)->buildAsync(self::seams($app));
    }

    private static function seams(Container $app): AsyncGraphSeams
    {
        return new AsyncGraphSeams(
            stateStore: $app->make(StateStoreInterface::class),
            queueDriver: $app->make(QueueDriverInterface::class),
            eventLedger: $app->make(EventLedgerInterface::class),
            executionRegistry: $app->make(ExecutionRegistryInterface::class),
            pendingCorrelationRegistry: $app->make(PendingCorrelationRegistryInterface::class),
            definitionRegistry: $app->make(DefinitionRegistryInterface::class),
            lockManager: $app->make(LockManagerInterface::class),
            httpClient: $app->make(HttpClientInterface::class),
            openApiExecutor: $app->bound(OpenApiExecutorInterface::class) ? $app->make(OpenApiExecutorInterface::class) : null,
            expressionResolver: $app->bound(ExpressionResolverInterface::class) ? $app->make(ExpressionResolverInterface::class) : null,
            requestFactory: $app->bound(RequestFactoryInterface::class) ? $app->make(RequestFactoryInterface::class) : null,
            logger: $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null,
            idempotencyEnabled: ConfigValue::bool(config('arazzo.idempotency.enabled', false), false),
            idempotencyHeader: ConfigValue::string(config('arazzo.idempotency.header', 'Idempotency-Key'), 'Idempotency-Key'),
            strictValidation: ConfigValue::bool(config('arazzo.strict_schema_validation', false), false),
            retryCeiling: ConfigValue::int(config('arazzo.retry_ceiling', 10), 10),
            retryBackoffMultiplier: ConfigValue::float(config('arazzo.retry_backoff_multiplier', 1.0), 1.0),
            stateTtlSeconds: ConfigValue::int(config('arazzo.state_ttl', 86400), 86400),
        );
    }
}
