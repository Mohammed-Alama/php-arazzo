<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Lock;

use Alama\Arazzo\State\Interfaces\LockManagerInterface;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

class LaravelRedisLockManager implements LockManagerInterface
{
    /** @var array<string, Lock> locks owned by this process via tryAcquire */
    private array $owned = [];

    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return Cache::lock($key, $ttlSeconds)->block(5, $callback);
    }

    public function tryAcquire(string $key, int $ttlSeconds): bool
    {
        $lock = Cache::lock($key, $ttlSeconds);

        if (!$lock->get()) {
            return false;
        }

        $this->owned[$key] = $lock;

        return true;
    }

    public function release(string $key): void
    {
        $lock = $this->owned[$key] ?? null;

        if ($lock !== null) {
            unset($this->owned[$key]);
            $lock->release();

            return;
        }

        // Best effort for foreign keys: without the owner token this is a no-op.
        Cache::lock($key, 0)->release();
    }
}
