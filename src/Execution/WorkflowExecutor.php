<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionLoggerInterface;
use Alama\LaravelArazzo\Execution\Dto\ExecutionResult;

class WorkflowExecutor
{
    public function __construct(
        private StepExecutor $stepExecutor,
        private ?ExecutionLoggerInterface $logger = null,
    ) {
    }

    public function execute(Workflow $workflow, ArazzoDocument $document, array $inputs): ExecutionResult
    {
        // Components should ideally be populated from $document, but keep simple for now
        $context = new WorkflowContext($workflow->workflowId, $inputs);

        $stepResults = [];

        foreach ($workflow->steps as $step) {
            $stepId = $step->stepId;

            $this->logger?->logStepStarted($stepId);

            [$context, $success] = $this->stepExecutor->execute($step, $context, $document);

            $outputs = $context->getSteps()[$stepId]['outputs'] ?? [];
            $result = new \Alama\LaravelArazzo\Execution\Dto\StepResult($stepId, $success, $outputs);
            
            $stepResults[$stepId] = $result;

            if (!$success) {
                $this->logger?->logStepFailed($stepId, new \RuntimeException('Step failed'));
                break;
            }

            $this->logger?->logStepCompleted($stepId, $result->outputs);
        }

        return new ExecutionResult($workflow->workflowId, 'completed', [], $stepResults);
    }
}
