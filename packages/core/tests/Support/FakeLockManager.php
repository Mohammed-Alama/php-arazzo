<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Support;

use Alama\Arazzo\Runner\Execution\Contracts\LockManagerInterface;

final class FakeLockManager implements LockManagerInterface
{
    public int $acquisitions = 0;

    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $this->acquisitions++;

        return $callback();
    }
}
