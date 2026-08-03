<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution\Contracts;

interface LockManagerInterface
{
    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed;
}
