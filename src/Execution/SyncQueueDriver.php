<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;

class SyncQueueDriver implements QueueDriverInterface
{
    public array $dispatched = [];

    public function dispatch(object $job, int $delaySeconds = 0): void
    {
        $this->dispatched[] = [
            'job' => $job,
            'delaySeconds' => $delaySeconds,
        ];
    }
}
