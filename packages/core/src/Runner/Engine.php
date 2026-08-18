<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Events\Dispatcher\NullEventDispatcher;
use Alama\Arazzo\Events\RunStarted;
use Alama\Arazzo\Runner\Contracts\QueueDriverInterface;
use Alama\Arazzo\Runner\Contracts\StateStoreInterface;
use Alama\Arazzo\Runner\Jobs\ExecuteStepJob;
use Psr\EventDispatcher\EventDispatcherInterface;

class Engine
{
    private EventDispatcherInterface $events;

    /** @var array<string, true> executionIds for which RunStarted has fired */
    private array $started = [];

    public function __construct(
        private QueueDriverInterface $queueDriver,
        /** @phpstan-ignore property.onlyWritten */
        private StateStoreInterface $stateStore,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new NullEventDispatcher();
    }

    public function evaluate(Workflow $workflow, WorkflowContext $context): void
    {
        if ($context->getWorkflowId() === null) {
            $context = $context->withWorkflowId($workflow->workflowId);
        }

        $executionId = $context->getExecutionId();
        if ($executionId !== null && !isset($this->started[$executionId])) {
            $this->started[$executionId] = true;
            $this->events->dispatch(new RunStarted(
                $executionId,
                $workflow->workflowId,
                $context->getDefinitionId(),
                $context->getInputs(),
                new \DateTimeImmutable(),
            ));
        }

        $graph = new DependencyGraph($workflow->steps);
        $analyzer = new DependencyAnalyzer($graph);
        $runnableSteps = $analyzer->getRunnableSteps($context);

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
