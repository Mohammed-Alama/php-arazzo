<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class RedisHotStateStore implements StateStoreInterface
{
    public function __construct(private RedisFactory $redis, private string $prefix = 'arazzo:state:') {}

    public function save(string $id, array $state): void
    {
        $this->redis->connection()->set($this->prefix . $id, json_encode($state));
    }

    public function load(string $id): array
    {
        $data = $this->redis->connection()->get($this->prefix . $id);
        return $data ? json_decode($data, true) : [];
    }
}
