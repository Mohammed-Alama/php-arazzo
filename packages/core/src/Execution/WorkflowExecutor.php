<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Events\Dispatcher\NullEventDispatcher;
use Alama\Arazzo\Events\RunCompleted;
use Alama\Arazzo\Events\RunFailed;
use Alama\Arazzo\Events\RunStarted;
use Alama\Arazzo\Events\StepExecuted as StepExecutedEvent;
use Alama\Arazzo\Events\StepFailed as StepFailedEvent;
use Alama\Arazzo\Events\StepStarted;
use Alama\Arazzo\Execution\Contracts\ExecutionLoggerInterface;
use Alama\Arazzo\Execution\Dto\ExecutionResult;
use Alama\Arazzo\Execution\Dto\StepResult;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

class WorkflowExecutor
{
    private EventDispatcherInterface $events;

    public function __construct(
        private StepExecutor $stepExecutor,
        private ?ExecutionLoggerInterface $logger = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new NullEventDispatcher();
    }

    /**
     * @param array<string, mixed> $inputs
     */
    public function execute(Workflow $workflow, ArazzoDocument $document, array $inputs, ?WorkflowContext $context = null): ExecutionResult
    {
        $executionId = $inputs['__executionId'] ?? bin2hex(random_bytes(8));
        $context ??= new WorkflowContext($workflow->workflowId, $inputs);

        $this->events->dispatch(new RunStarted(
            $executionId,
            $workflow->workflowId,
            $workflow->workflowId,
            $inputs,
            new \DateTimeImmutable(),
        ));

        $stepResults = [];
        try {
            $graph = new DependencyGraph($workflow->steps);
            foreach ($graph->getTopologicalOrder() as $stepId) {
                $step = $graph->getStepsById()[$stepId];

                $this->logger?->logStepStarted($stepId);
                $this->events->dispatch(new StepStarted(
                    $executionId,
                    $workflow->workflowId,
                    $stepId,
                    1,
                    new \DateTimeImmutable(),
                ));

                [$context, $success] = $this->stepExecutor->execute($step, $context, $document);

                $outputs = $context->getSteps()[$stepId]['outputs'] ?? [];
                $result = new StepResult($stepId, $success, $outputs);

                $stepResults[$stepId] = $result;

                if (!$success) {
                    $cause = new \RuntimeException("Step '{$stepId}' failed");
                    $this->logger?->logStepFailed($stepId, $cause);
                    $this->events->dispatch(new StepFailedEvent(
                        $executionId,
                        $workflow->workflowId,
                        $stepId,
                        $cause,
                        new \DateTimeImmutable(),
                    ));
                    $this->events->dispatch(new RunFailed(
                        $executionId,
                        $workflow->workflowId,
                        $cause,
                        new \DateTimeImmutable(),
                    ));

                    return new ExecutionResult($workflow->workflowId, 'failed', [], $stepResults);
                }

                $this->events->dispatch(new StepExecutedEvent(
                    $executionId,
                    $workflow->workflowId,
                    $stepId,
                    (int) ($context->getSteps()[$stepId]['statusCode'] ?? 0),
                    $outputs,
                    true,
                    new \DateTimeImmutable(),
                ));
                $this->logger?->logStepCompleted($workflow->workflowId, $stepId, $result->outputs);
            }
        } catch (Throwable $t) {
            $this->events->dispatch(new RunFailed(
                $executionId,
                $workflow->workflowId,
                $t,
                new \DateTimeImmutable(),
            ));

            throw $t;
        }

        $aggregatedOutputs = [];
        foreach ($stepResults as $sid => $r) {
            $aggregatedOutputs[$sid] = $r->outputs;
        }
        $this->events->dispatch(new RunCompleted(
            $executionId,
            $workflow->workflowId,
            $aggregatedOutputs,
            new \DateTimeImmutable(),
        ));

        return new ExecutionResult($workflow->workflowId, 'completed', [], $stepResults);
    }
}
