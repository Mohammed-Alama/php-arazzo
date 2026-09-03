<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

interface LockStrategyInterface
{
    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed;

    public function tryAcquire(string $key, int $ttlSeconds): bool;

    public function release(string $key): void;
}
