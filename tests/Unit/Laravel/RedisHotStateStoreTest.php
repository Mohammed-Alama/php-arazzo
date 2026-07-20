<?php

declare(strict_types=1);

namespace Tests\Unit\Laravel;

use Alama\LaravelArazzo\Laravel\RedisHotStateStore;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;

function makeRecordingRedisConnection(): Connection
{
    return new class() extends Connection
    {
        /** @var list<array<string, mixed>> */
        public array $calls = [];

        public function __construct()
        {
        }

        public function set($key, $value)
        {
            $this->calls[] = ['method' => 'set', 'key' => $key, 'value' => $value];
        }

        public function setex($key, $seconds, $value)
        {
            $this->calls[] = ['method' => 'setex', 'key' => $key, 'seconds' => $seconds, 'value' => $value];
        }

        public function get($key)
        {
            $this->calls[] = ['method' => 'get', 'key' => $key];

            return json_encode(['foo' => 'bar']);
        }

        public function createSubscription($channels, \Closure $callback, $method = 'subscribe')
        {
        }
    };
}

it('saves with the default TTL and loads state back', function (): void {
    $redisConnection = makeRecordingRedisConnection();
    $factory = $this->createMock(RedisFactory::class);
    $factory->method('connection')->willReturn($redisConnection);

    $store = new RedisHotStateStore($factory, defaultTtlSeconds: 3600);
    $store->save('exec_123', ['foo' => 'bar']);
    $result = $store->load('exec_123');

    expect($result)->toEqual(['foo' => 'bar']);
    expect($redisConnection->calls[0]['method'])->toBe('setex');
    expect($redisConnection->calls[0]['key'])->toBe('arazzo:state:exec_123');
    expect($redisConnection->calls[0]['seconds'])->toBe(3600);
    expect($redisConnection->calls[0]['value'])->toBe(json_encode(['foo' => 'bar']));
});

it('lets an explicit TTL override the default', function (): void {
    $redisConnection = makeRecordingRedisConnection();
    $factory = $this->createMock(RedisFactory::class);
    $factory->method('connection')->willReturn($redisConnection);

    $store = new RedisHotStateStore($factory, defaultTtlSeconds: 3600);
    $store->save('exec_123', ['foo' => 'bar'], ttlSeconds: 60);

    expect($redisConnection->calls[0]['seconds'])->toBe(60);
});

it('returns null when the key is missing', function (): void {
    $redisConnection = new class() extends Connection
    {
        public function __construct()
        {
        }

        public function get($key)
        {
            return null;
        }

        public function createSubscription($channels, \Closure $callback, $method = 'subscribe')
        {
        }
    };
    $factory = $this->createMock(RedisFactory::class);
    $factory->method('connection')->willReturn($redisConnection);

    $store = new RedisHotStateStore($factory);

    expect($store->load('missing'))->toBeNull();
});
