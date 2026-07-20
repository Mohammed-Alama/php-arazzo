<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Jobs\ResumeCorrelationJob;

class CorrelationResumer
{
    public function __construct(
        private PendingCorrelationRegistryInterface $pendingCorrelations,
        private StateStoreInterface $stateStore,
        private DefinitionRegistryInterface $definitionRegistry,
        private ExpressionResolverInterface $expressionResolver,
        private StepOutcomeHandler $outcomeHandler,
        private EventLedgerInterface $eventLedger,
    ) {
    }

    public function resume(ResumeCorrelationJob $job): void
    {
        $correlation = $this->pendingCorrelations->findByCorrelationId($job->correlationId);
        if ($correlation === null) {
            return;
        }

        $executionId = $correlation->executionId;
        $persisted = $this->stateStore->load($executionId);
        if ($persisted === null) {
            $this->eventLedger->append($executionId, 'execution.state_missing', ['correlationId' => $job->correlationId]);

            return;
        }

        $document = $this->definitionRegistry->get((string) $persisted['definitionId']);
        if ($document === null) {
            $this->eventLedger->append($executionId, 'execution.definition_missing', ['definitionId' => $persisted['definitionId']]);

            return;
        }

        $workflow = $this->findWorkflow($document, (string) $persisted['workflowId']);
        if ($workflow === null) {
            $this->eventLedger->append($executionId, 'execution.workflow_missing', ['workflowId' => $persisted['workflowId']]);

            return;
        }

        $step = $this->findStep($workflow, $correlation->stepId);
        if ($step === null) {
            $this->eventLedger->append($executionId, 'execution.step_missing', ['stepId' => $correlation->stepId]);

            return;
        }

        $context = new WorkflowContext(
            (string) $persisted['definitionId'],
            (array) ($persisted['inputs'] ?? []),
            (array) ($persisted['steps'] ?? []),
            (array) ($persisted['components'] ?? []),
            (string) $persisted['workflowId'],
            $executionId,
        );

        $contextWithResponse = $context->withStepResponse($step->stepId, [
            'statusCode' => 200,
            'body' => $job->payload,
        ]);
        $outputs = $this->expressionResolver->extractOutputs($step, $contextWithResponse, $document);

        $contextWithResult = $context->withStepResult($step->stepId, [
            'statusCode' => 200,
            'response' => ['statusCode' => 200, 'body' => $job->payload],
            'outputs' => $outputs,
        ]);

        $this->pendingCorrelations->consume($job->correlationId);

        $criteriaMet = $this->expressionResolver->evaluateSuccessCriteria($step, $contextWithResult, $document);

        $this->stateStore->save($executionId, [
            'definitionId' => $contextWithResult->getDefinitionId(),
            'workflowId' => $contextWithResult->getWorkflowId(),
            'steps' => $contextWithResult->getSteps(),
            'inputs' => $contextWithResult->getInputs(),
            'components' => $contextWithResult->getComponents(),
        ]);

        $this->eventLedger->append($executionId, 'step.resumed', ['stepId' => $step->stepId, 'correlationId' => $job->correlationId]);

        $this->outcomeHandler->handle($document, $workflow, $step, $contextWithResult, $executionId, $criteriaMet);
    }

    private function findWorkflow(ArazzoDocument $document, string $workflowId): ?Workflow
    {
        foreach ($document->workflows as $workflow) {
            if ($workflow->workflowId === $workflowId) {
                return $workflow;
            }
        }

        return null;
    }

    private function findStep(Workflow $workflow, string $stepId): ?Step
    {
        foreach ($workflow->steps as $step) {
            if ($step->stepId === $stepId) {
                return $step;
            }
        }

        return null;
    }
}
