<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runtime\Infrastructure;

use Alama\Arazzo\Contracts\Interfaces\LockStrategyInterface;

/**
 * Null object: every call succeeds and nothing is locked, so the interface's
 * parameters are accepted for parity and ignored by design.
 *
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
