<?php

declare(strict_types=1);

namespace Alama\Arazzo\Infrastructure;

use Alama\Arazzo\Interfaces\LockManagerInterface;
use Alama\Arazzo\Interfaces\LockStrategyInterface;

final class PessimisticLockStrategy implements LockStrategyInterface
{
    public function __construct(
        private LockManagerInterface $lockManager,
    ) {}

    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return $this->lockManager->acquire($key, $ttlSeconds, $callback);
    }

    public function tryAcquire(string $key, int $ttlSeconds): bool
    {
        return $this->lockManager->tryAcquire($key, $ttlSeconds);
    }

    public function release(string $key): void
    {
        $this->lockManager->release($key);
    }
}
