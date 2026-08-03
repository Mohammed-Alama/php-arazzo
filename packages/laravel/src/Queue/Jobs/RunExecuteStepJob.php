<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Queue\Jobs;

use Alama\Arazzo\Execution\Jobs\ExecuteStepJob;
use Alama\Arazzo\Execution\StepExecutionWorker;
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
