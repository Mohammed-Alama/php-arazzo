<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel\Jobs;

use Alama\LaravelArazzo\Execution\CorrelationResumer;
use Alama\LaravelArazzo\Execution\Jobs\ResumeCorrelationJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunResumeCorrelationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public ResumeCorrelationJob $inner)
    {
    }

    public function handle(CorrelationResumer $resumer): void
    {
        $resumer->resume($this->inner);
    }
}
