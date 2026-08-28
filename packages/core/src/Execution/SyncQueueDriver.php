<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution;

use Alama\Arazzo\Interfaces\QueueDriverInterface;

class SyncQueueDriver implements QueueDriverInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $dispatched = [];

    public function dispatch(object $job, int $delaySeconds = 0): void
    {
        $this->dispatched[] = [
            'job' => $job,
            'delaySeconds' => $delaySeconds,
        ];
    }
}
