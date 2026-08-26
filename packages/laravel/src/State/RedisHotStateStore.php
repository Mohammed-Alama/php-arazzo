<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\State;

use Alama\Arazzo\Contracts\StateStoreInterface;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class RedisHotStateStore implements StateStoreInterface
{
    public function __construct(
        private RedisFactory $redis,
        private string $prefix = 'arazzo:state:',
        private int $defaultTtlSeconds = 86400,
    ) {}

    /**
     * @param  array<string, mixed>  $state
     */
    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
        $ttl = $ttlSeconds ?? $this->defaultTtlSeconds;

        $this->redis->connection()->setex($this->prefix.$executionId, $ttl, json_encode($state));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $executionId): ?array
    {
        $data = $this->redis->connection()->get($this->prefix.$executionId);

        return $data ? json_decode($data, true) : null;
    }
}
