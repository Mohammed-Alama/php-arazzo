<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;
use Alama\LaravelArazzo\Events\CorrelationResumed;
use Alama\LaravelArazzo\Events\Dispatcher\NullEventDispatcher;
use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class CorrelationResumer
{
    private EventDispatcherInterface $events;

    public function __construct(
        private PendingCorrelationRegistryInterface $pendingCorrelations,
        private StateStoreInterface $stateStore,
        private DefinitionRegistryInterface $definitionRegistry,
        private ExpressionResolverInterface $expressionResolver,
        private StepOutcomeHandler $outcomeHandler,
        private EventLedgerInterface $eventLedger,
        private LockManagerInterface $lockManager,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new NullEventDispatcher();
    }

    /**
     * @param array{statusCode?: int, headers?: array<string, mixed>, body?: mixed} $response
     */
    public function resume(string $correlationId, array $response): void
    {
        $correlation = $this->pendingCorrelations->findByCorrelationId($correlationId);
        if ($correlation === null) {
            return;
        }

        $executionId = $correlation->executionId;
        $lockKey = "execution_lock_{$executionId}";

        $this->lockManager->acquire($lockKey, 30, function () use ($correlationId, $correlation, $response, $executionId) {
            $persisted = $this->stateStore->load($executionId);
            if ($persisted === null) {
                $this->eventLedger->append($executionId, 'execution.state_missing', ['correlationId' => $correlationId]);

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

            $statusCode = $response['statusCode'] ?? 200;
            $body = $response['body'] ?? null;

            $contextWithResponse = $context->withStepResponse($step->stepId, [
                'statusCode' => $statusCode,
                'body' => $body,
            ]);
            $outputs = $this->expressionResolver->extractOutputs($step, $contextWithResponse, $document);

            $contextWithResult = $context->withStepResult($step->stepId, [
                'statusCode' => $statusCode,
                'response' => $response,
                'outputs' => $outputs,
            ]);

            $this->pendingCorrelations->consume($correlationId);

            $this->events->dispatch(new CorrelationResumed(
                $executionId, $workflow->workflowId, $step->stepId, $correlationId, new \DateTimeImmutable(),
            ));

            $criteriaMet = $this->expressionResolver->evaluateSuccessCriteria($step, $contextWithResult, $document);

            $this->stateStore->save($executionId, [
                'definitionId' => $contextWithResult->getDefinitionId(),
                'workflowId' => $contextWithResult->getWorkflowId(),
                'steps' => $contextWithResult->getSteps(),
                'inputs' => $contextWithResult->getInputs(),
                'components' => $contextWithResult->getComponents(),
            ]);

            $this->eventLedger->append($executionId, 'step.resumed', ['stepId' => $step->stepId, 'correlationId' => $correlationId]);

            $this->outcomeHandler->handle($document, $workflow, $step, $contextWithResult, $executionId, $criteriaMet);
        });
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
