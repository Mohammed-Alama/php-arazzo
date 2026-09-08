<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Infrastructure;

use Alama\Arazzo\Contracts\Interfaces\LockStrategyInterface;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
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
