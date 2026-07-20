<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel;

use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Execution\Jobs\ResumeCorrelationJob;
use Alama\LaravelArazzo\Laravel\Jobs\RunExecuteStepJob;
use Alama\LaravelArazzo\Laravel\Jobs\RunResumeCorrelationJob;
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
