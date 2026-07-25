<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionLoggerInterface;
use Alama\LaravelArazzo\Execution\Dto\ExecutionResult;
use Alama\LaravelArazzo\Execution\Dto\StepResult;

class WorkflowExecutor
{
    public function __construct(
        private StepExecutor $stepExecutor,
        private ?ExecutionLoggerInterface $logger = null,
    ) {
    }

    /**
     * @param array<string, mixed> $inputs
     */
    public function execute(Workflow $workflow, ArazzoDocument $document, array $inputs, ?WorkflowContext $context = null): ExecutionResult
    {
        // Components should ideally be populated from $document, but keep simple for now
        $context ??= new WorkflowContext($workflow->workflowId, $inputs);

        $stepResults = [];
        $graph = new DependencyGraph($workflow->steps);

        foreach ($graph->getTopologicalOrder() as $stepId) {
            $step = $graph->getStepsById()[$stepId];

            $this->logger?->logStepStarted($stepId);

            [$context, $success] = $this->stepExecutor->execute($step, $context, $document);

            $outputs = $context->getSteps()[$stepId]['outputs'] ?? [];
            $result = new StepResult($stepId, $success, $outputs);

            $stepResults[$stepId] = $result;

            if (!$success) {
                $this->logger?->logStepFailed($stepId, new \RuntimeException('Step failed'));
                break;
            }

            $this->logger?->logStepCompleted($workflow->workflowId, $stepId, $result->outputs);
        }

        return new ExecutionResult($workflow->workflowId, 'completed', [], $stepResults);
    }
}
