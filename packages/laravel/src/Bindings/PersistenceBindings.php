<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Bindings;

use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\LockStrategyInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Document\Parser\Parser;
use Alama\Arazzo\Laravel\Lock\LaravelRedisLockManager;
use Alama\Arazzo\Laravel\Persistence\DatabaseDefinitionRegistry;
use Alama\Arazzo\Laravel\Persistence\DatabaseEventLedger;
use Alama\Arazzo\Laravel\Persistence\DatabaseExecutionRegistry;
use Alama\Arazzo\Laravel\Persistence\DatabasePendingCorrelationRegistry;
use Alama\Arazzo\Laravel\Queue\LaravelQueueDriver;
use Alama\Arazzo\Laravel\State\RedisHotStateStore;
use Alama\Arazzo\Laravel\Support\ConfigValue;
use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\StateStoreInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Psr\Log\LoggerInterface;

/**
 * Durable-execution persistence ports: hot state, ledger, registries,
 * pending correlations, plus the queue/lock transports.
 */
final class PersistenceBindings
{
    public static function register(Container $app): void
    {
        $app->singleton(StateStoreInterface::class, function (Container $app) {
            return new RedisHotStateStore(
                $app->make(RedisFactory::class),
                defaultTtlSeconds: ConfigValue::int(config('arazzo.state_ttl', 86400), 86400),
            );
        });

        $app->singleton(EventLedgerInterface::class, function (Container $app) {
            return new DatabaseEventLedger(
                $app->make('db')->connection(),
                ConfigValue::string(config('arazzo.events_table', 'arazzo_events'), 'arazzo_events'),
                $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null,
            );
        });

        $app->singleton(DefinitionRegistryInterface::class, function (Container $app) {
            return new DatabaseDefinitionRegistry(
                $app->make('db')->connection(),
                new Parser(),
                ConfigValue::string(config('arazzo.definitions_table', 'arazzo_definitions'), 'arazzo_definitions'),
            );
        });

        $app->singleton(ExecutionRegistryInterface::class, function (Container $app) {
            return new DatabaseExecutionRegistry(
                $app->make('db')->connection(),
                ConfigValue::string(config('arazzo.executions_table', 'arazzo_executions'), 'arazzo_executions'),
            );
        });

        $app->singleton(LockManagerInterface::class, LaravelRedisLockManager::class);
        // New code may inject the strategy name; same underlying singleton.
        $app->singleton(LockStrategyInterface::class, LockManagerInterface::class);
        $app->singleton(QueueDriverInterface::class, LaravelQueueDriver::class);

        $app->singleton(PendingCorrelationRegistryInterface::class, function (Container $app) {
            return new DatabasePendingCorrelationRegistry(
                $app->make('db')->connection(),
                ConfigValue::string(config('arazzo.pending_correlations_table', 'arazzo_pending_correlations'), 'arazzo_pending_correlations'),
            );
        });
    }
}
