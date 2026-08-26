<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Queue;

use Alama\Arazzo\Contracts\QueueDriverInterface;
use Alama\Arazzo\Laravel\Queue\Jobs\RunExecuteStepJob;
use Alama\Arazzo\Laravel\Queue\Jobs\RunResumeCorrelationJob;
use Alama\Arazzo\Runner\Jobs\ExecuteStepJob;
use Alama\Arazzo\Runner\Jobs\ResumeCorrelationJob;
use Illuminate\Support\Facades\Queue;

class LaravelQueueDriver implements QueueDriverInterface
{
    public function dispatch(object $job, int $delaySeconds = 0): void
    {
        $wrapped = match (true) {
            $job instanceof ExecuteStepJob => new RunExecuteStepJob($job),
            $job instanceof ResumeCorrelationJob => new RunResumeCorrelationJob($job),
            default => $job,
        };

        if ($delaySeconds > 0) {
            Queue::later(now()->addSeconds($delaySeconds), $wrapped);
        } else {
            Queue::push($wrapped);
        }
    }
}
