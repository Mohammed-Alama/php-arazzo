<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
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
