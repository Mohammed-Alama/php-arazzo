<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Support;

use Alama\Arazzo\Interfaces\LockManagerInterface;

final class FakeLockManager implements LockManagerInterface
{
    public int $acquisitions = 0;

    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $this->acquisitions++;

        return $callback();
    }

    public function tryAcquire(string $key, int $ttlSeconds): bool
    {
        $this->acquisitions++;

        return true;
    }

    public function release(string $key): void {}
}
