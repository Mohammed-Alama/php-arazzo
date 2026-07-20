<?php
namespace Tests\Unit\Laravel;

use Alama\LaravelArazzo\Laravel\RedisHotStateStore;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use PHPUnit\Framework\TestCase;

class RedisHotStateStoreTest extends TestCase
{
    public function test_saves_and_loads_state(): void
    {
        $redisConnection = new class extends Connection {
            public $calls = [];
            public function __construct() {}
            public function set($key, $value) {
                $this->calls[] = ['method' => 'set', 'key' => $key, 'value' => $value];
            }
            public function get($key) {
                $this->calls[] = ['method' => 'get', 'key' => $key];
                return json_encode(['foo' => 'bar']);
            }
            public function createSubscription($channels, \Closure $callback, $method = 'subscribe') {}
        };

        $factory = $this->createMock(RedisFactory::class);
        $factory->method('connection')->willReturn($redisConnection);

        $store = new RedisHotStateStore($factory);
        $store->save('wf_123', ['foo' => 'bar']);
        $result = $store->load('wf_123');
        
        $this->assertEquals(['foo' => 'bar'], $result);
        $this->assertEquals('set', $redisConnection->calls[0]['method']);
        $this->assertEquals('arazzo:state:wf_123', $redisConnection->calls[0]['key']);
        $this->assertEquals(json_encode(['foo' => 'bar']), $redisConnection->calls[0]['value']);
        $this->assertEquals('get', $redisConnection->calls[1]['method']);
        $this->assertEquals('arazzo:state:wf_123', $redisConnection->calls[1]['key']);
    }
}
