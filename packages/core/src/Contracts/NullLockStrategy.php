<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts;

final class NullLockStrategy implements LockStrategyInterface
{
    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return $callback();
    }

    public function tryAcquire(string $key, int $ttlSeconds): bool
    {
        return true;
    }

    public function release(string $key): void {}
}
