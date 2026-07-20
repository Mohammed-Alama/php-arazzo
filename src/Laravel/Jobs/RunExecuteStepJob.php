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

    public string $definitionId;

    public ?string $workflowId;

    public ?string $executionId;

    public function __construct(public ExecuteStepJob $inner)
    {
        $this->definitionId = $this->inner->context->getDefinitionId();
        $this->workflowId = $this->inner->context->getWorkflowId();
        $this->executionId = $this->inner->context->getExecutionId();
    }

    public function handle(StepExecutionWorker $worker): void
    {
        $worker->handle($this->inner);
    }
}
