<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Queue\Jobs;

use Alama\Arazzo\Runner\Async\ResumeCorrelationJob;
use Alama\Arazzo\Runner\AsyncExecutionGraph;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
class RunResumeCorrelationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public ResumeCorrelationJob $inner) {}

    public function handle(AsyncExecutionGraph $graph): void
    {
        $graph->resumer()->resume($this->inner->correlationId, $this->inner->response);
    }
}
