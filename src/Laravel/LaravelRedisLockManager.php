<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Illuminate\Support\Facades\Cache;

class LaravelRedisLockManager implements LockManagerInterface
{
    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return Cache::lock($key, $ttlSeconds)->block(5, $callback);
    }
}
