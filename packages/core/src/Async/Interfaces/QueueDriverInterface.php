<?php

declare(strict_types=1);

namespace Alama\Arazzo\Async\Interfaces;

// Framework port (kept as a seam): queue transports vary (Laravel queue, sync test driver).

interface QueueDriverInterface
{
    public function dispatch(object $job, int $delaySeconds = 0): void;
}
