<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;

class Engine
{
    public function __construct(
        private DependencyAnalyzer $analyzer,
        private QueueDriverInterface $queueDriver,
        private StateStoreInterface $stateStore,
    ) {
    }

    public function evaluate(Workflow $workflow, WorkflowContext $context): void
    {
        $runnableSteps = $this->analyzer->getRunnableSteps($workflow->steps, $context);

        if (empty($runnableSteps)) {
            // Workflow complete or waiting. We will handle completion logic later.
            return;
        }

        foreach ($runnableSteps as $step) {
            $job = new ExecuteStepJob($step, $context);
            $this->queueDriver->dispatch($job);
        }
    }
}
