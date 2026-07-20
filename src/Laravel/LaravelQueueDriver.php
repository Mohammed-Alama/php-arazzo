<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Illuminate\Support\Facades\Queue;

class LaravelQueueDriver implements QueueDriverInterface
{
    public function dispatch(object $job, int $delaySeconds = 0): void
    {
        if ($delaySeconds > 0) {
            Queue::later(now()->addSeconds($delaySeconds), $job);
        } else {
            Queue::push($job);
        }
    }
}
