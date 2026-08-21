<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Events\Dispatcher\NullEventDispatcher;
use Alama\Arazzo\Events\RunCompleted;
use Alama\Arazzo\Events\RunFailed;
use Alama\Arazzo\Events\RunStarted;
use Alama\Arazzo\Events\StepExecuted as StepExecutedEvent;
use Alama\Arazzo\Events\StepFailed as StepFailedEvent;
use Alama\Arazzo\Events\StepStarted;
use Alama\Arazzo\Runner\Contracts\ExecutionLoggerInterface;
use Alama\Arazzo\Runner\Dto\ExecutionResult;
use Alama\Arazzo\Runner\Dto\ExecutionState;
use Alama\Arazzo\Runner\Dto\StepResult;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

class WorkflowExecutor
{
    private EventDispatcherInterface $events;

    public function __construct(
        private StepExecutor $stepExecutor,
        private ?ExecutionLoggerInterface $logger = null,
        ?EventDispatcherInterface $events = null,
        private ?WorkflowEngine $workflowEngine = null,
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
        if ($this->workflowEngine !== null) {
            return $this->executeCanonically($workflow, $document, $inputs, $context, $executionId);
        }
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

    /** @param array<string, mixed> $inputs */
    private function executeCanonically(Workflow $workflow, ArazzoDocument $document, array $inputs, ?WorkflowContext $context, string $executionId): ExecutionResult
    {
        $state = ExecutionState::start($executionId, $context?->getDefinitionId() ?? $workflow->workflowId, $workflow->workflowId, $inputs, components: $context?->getComponents() ?? []);
        $currentWorkflow = $workflow;
        $stepId = $this->firstStep($workflow);
        $results = [];
        try {
            while ($stepId !== null) {
                $step = $this->step($currentWorkflow, $stepId);
                if ($step === null) {
                    throw new \LogicException("Unknown step '{$stepId}' in workflow '{$currentWorkflow->workflowId}'.");
                }
                $attempt = $state->attemptFor($stepId) + 1;
                $this->logger?->logStepStarted($stepId);
                $this->events->dispatch(new StepStarted($executionId, $currentWorkflow->workflowId, $stepId, $attempt, new \DateTimeImmutable()));
                [$stepContext, $success] = $this->stepExecutor->execute($step, new WorkflowContext($state->definitionId, $state->inputs, $state->stepResults, $state->components, $state->workflowId, $state->executionId), $document);
                $raw = $stepContext->getSteps()[$stepId] ?? [];
                $state = $state->withStepResult($stepId, $raw);
                $result = new StepResult($stepId, $success, $raw['outputs'] ?? []);
                $results[$stepId] = $result;
                $transition = $this->workflowEngine->transition($document, $currentWorkflow, $step, $state, $success);
                $state = $transition->state;
                $this->events->dispatch(new StepExecutedEvent($executionId, $currentWorkflow->workflowId, $stepId, (int) ($raw['statusCode'] ?? 0), $result->outputs, $success, new \DateTimeImmutable()));
                if ($transition->isTerminal()) {
                    if ($transition->status === 'failed') {
                        $this->events->dispatch(new RunFailed($executionId, $currentWorkflow->workflowId, new \RuntimeException("Workflow '{$currentWorkflow->workflowId}' failed at step '{$stepId}'."), new \DateTimeImmutable()));
                    } else {
                        $this->events->dispatch(new RunCompleted($executionId, $currentWorkflow->workflowId, $state->outputs, new \DateTimeImmutable()));
                    }

                    return new ExecutionResult($currentWorkflow->workflowId, $transition->status ?? 'failed', $state->outputs, $results);
                }
                if ($transition->workflowId !== null && $transition->workflowId !== $currentWorkflow->workflowId) {
                    $currentWorkflow = $this->workflow($document, $transition->workflowId);
                }
                $stepId = $transition->stepId ?? $this->firstStep($currentWorkflow);
            }
        } catch (Throwable $t) {
            $this->events->dispatch(new RunFailed($executionId, $currentWorkflow->workflowId, $t, new \DateTimeImmutable()));
            throw $t;
        }

        return new ExecutionResult($currentWorkflow->workflowId, 'succeeded', $state->outputs, $results);
    }

    private function firstStep(Workflow $workflow): ?string
    {
        return $workflow->steps[0]->stepId ?? null;
    }

    private function step(Workflow $workflow, string $id): ?Step
    {
        foreach ($workflow->steps as $step) {
            if ($step->stepId === $id) {
                return $step;
            }
        }

return null;
    }

    private function workflow(ArazzoDocument $document, string $id): Workflow
    {
        foreach ($document->workflows as $workflow) {
            if ($workflow->workflowId === $id) {
                return $workflow;
            }
        } throw new \LogicException("Unknown workflow '{$id}'.");
    }
}
