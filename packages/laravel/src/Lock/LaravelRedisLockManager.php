<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Lock;

use Alama\Arazzo\Execution\Contracts\LockManagerInterface;
use Illuminate\Support\Facades\Cache;

class LaravelRedisLockManager implements LockManagerInterface
{
    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return Cache::lock($key, $ttlSeconds)->block(5, $callback);
    }
}
