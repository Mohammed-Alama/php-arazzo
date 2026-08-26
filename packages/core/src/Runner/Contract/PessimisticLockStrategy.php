<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Contract;

use Alama\Arazzo\Runner\Execution\Contracts\LockManagerInterface;

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
