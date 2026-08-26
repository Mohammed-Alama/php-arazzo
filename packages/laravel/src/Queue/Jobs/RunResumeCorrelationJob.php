<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Queue\Jobs;

use Alama\Arazzo\Execution\CorrelationResumer;
use Alama\Arazzo\Jobs\ResumeCorrelationJob;
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

    public function __construct(public ResumeCorrelationJob $inner) {}

    public function handle(CorrelationResumer $resumer): void
    {
        $resumer->resume($this->inner->correlationId, $this->inner->response);
    }
}
