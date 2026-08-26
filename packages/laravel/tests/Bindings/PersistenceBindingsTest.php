<?php

declare(strict_types=1);

use Alama\Arazzo\Laravel\Lock\LaravelRedisLockManager;
use Alama\Arazzo\Laravel\Persistence\DatabasePendingCorrelationRegistry;
use Alama\Arazzo\Laravel\Queue\LaravelQueueDriver;
use Alama\Arazzo\Laravel\State\RedisHotStateStore;
use Alama\Arazzo\Runner\Context\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\Context\Contracts\StateStoreInterface;
use Alama\Arazzo\Runner\Contract\LockStrategyInterface;
use Alama\Arazzo\Runner\Execution\Contracts\DefinitionRegistryInterface;
use Alama\Arazzo\Runner\Execution\Contracts\EventLedgerInterface;
use Alama\Arazzo\Runner\Execution\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\Execution\Contracts\LockManagerInterface;
use Alama\Arazzo\Runner\Execution\Contracts\QueueDriverInterface;

function bindingsProp(object $instance, string $name): mixed
{
    $prop = new ReflectionProperty($instance, $name);
    $prop->setAccessible(true);

    return $prop->getValue($instance);
}

it('binds each persistence port as a singleton', function (): void {
    foreach ([
        StateStoreInterface::class,
        EventLedgerInterface::class,
        DefinitionRegistryInterface::class,
        ExecutionRegistryInterface::class,
        PendingCorrelationRegistryInterface::class,
        QueueDriverInterface::class,
    ] as $abstract) {
        expect(app($abstract))->toBe(app($abstract));
    }
});

it('aliases LockStrategyInterface onto the same lock-manager instance', function (): void {
    $manager = app(LockManagerInterface::class);
    $strategy = app(LockStrategyInterface::class);

    expect($manager)->toBeInstanceOf(LaravelRedisLockManager::class)
        ->and($strategy)->toBe($manager);
});

it('feeds state_ttl from config into the hot-state store', function (): void {
    config()->set('arazzo.state_ttl', 4321);
    app()->forgetInstance(StateStoreInterface::class);

    $store = app(StateStoreInterface::class);

    expect($store)->toBeInstanceOf(RedisHotStateStore::class)
        ->and(bindingsProp($store, 'defaultTtlSeconds'))->toBe(4321);
});

it('falls back to the default ttl when config holds junk', function (): void {
    config()->set('arazzo.state_ttl', 'not-a-number');
    app()->forgetInstance(StateStoreInterface::class);

    expect(bindingsProp(app(StateStoreInterface::class), 'defaultTtlSeconds'))->toBe(86400);
});

it('feeds the events table name from config into the ledger', function (): void {
    config()->set('arazzo.events_table', 'custom_events');
    app()->forgetInstance(EventLedgerInterface::class);

    expect(bindingsProp(app(EventLedgerInterface::class), 'tableName'))->toBe('custom_events');
});

it('feeds the pending-correlations table name from config', function (): void {
    config()->set('arazzo.pending_correlations_table', 'corr_v2');
    app()->forgetInstance(PendingCorrelationRegistryInterface::class);

    expect(bindingsProp(app(PendingCorrelationRegistryInterface::class), 'table'))
        ->toBe('corr_v2')
        ->and(app(PendingCorrelationRegistryInterface::class))->toBeInstanceOf(DatabasePendingCorrelationRegistry::class)
        ->and(app(QueueDriverInterface::class))->toBeInstanceOf(LaravelQueueDriver::class);
});
