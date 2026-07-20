<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel\Jobs;

use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Execution\StepExecutionWorker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunExecuteStepJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public ExecuteStepJob $inner)
    {
    }

    public function handle(StepExecutionWorker $worker): void
    {
        $worker->handle($this->inner);
    }
}
