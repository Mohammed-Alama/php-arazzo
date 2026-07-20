<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Dto\ExecutionResult;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionLoggerInterface;

class WorkflowExecutor
{
    public function __construct(
        private StepExecutor $stepExecutor,
        private ?ExecutionLoggerInterface $logger = null
    ) {}

    public function execute(Workflow $workflow, array $inputs): ExecutionResult
    {
        $context = new VariableContext($inputs);

        $stepResults = [];

        foreach ($workflow->steps as $step) {
            $stepId = $step->stepId;
            
            $this->logger?->logStepStarted($stepId);
            
            $result = $this->stepExecutor->execute($step, $context);
            $stepResults[$stepId] = $result;

            if (!$result->success) {
                $this->logger?->logStepFailed($stepId, $result->error ?? new \RuntimeException("Step failed"));
                break;
            }
            
            $this->logger?->logStepCompleted($stepId, $result->outputs);
        }

        return new ExecutionResult($workflow->workflowId, 'completed', [], $stepResults);
    }


}
