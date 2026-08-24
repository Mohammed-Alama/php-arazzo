<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution\Contracts;

// Framework port (kept as a seam): locking is adapter-specific (Redis cache lock, DB advisory lock, in-process).

interface LockManagerInterface
{
    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed;
}
