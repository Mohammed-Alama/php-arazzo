<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Contracts\StepProtocolExecutorInterface;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;

class StepExecutionWorker
{
    /**
     * @param list<StepProtocolExecutorInterface> $protocolExecutors
     */
    public function __construct(
        private LockManagerInterface $lockManager,
        private StateStoreInterface $stateStore,
        private DefinitionRegistryInterface $definitionRegistry,
        private EventLedgerInterface $eventLedger,
        private ExecutionRegistryInterface $executionRegistry,
        private ExpressionResolverInterface $expressionResolver,
        private array $protocolExecutors,
        private StepOutcomeHandler $outcomeHandler,
        private ?LoggerInterface $logger = null,
        private int $stateTtlSeconds = 86400,
    ) {
    }

    public function handle(ExecuteStepJob $job): void
    {
        $step = $job->step;
        $executionId = $job->context->getExecutionId();

        if ($executionId === null) {
            throw new LogicException(
                "ExecuteStepJob for step '{$step->stepId}' has no executionId -- the workflow run was not initialized before dispatch."
            );
        }

        $lockKey = "execution_lock_{$executionId}";

        $this->lockManager->acquire($lockKey, 30, function () use ($step, $job, $executionId) {
            $context = $this->reconcileWithPersistedState($job->context, $executionId);

            if ($context->getStepStatus($step->stepId) === StepStatus::Succeeded) {
                return;
            }

            $document = $this->definitionRegistry->get($context->getDefinitionId());
            if ($document === null) {
                $this->eventLedger->append($executionId, 'execution.definition_missing', [
                    'definitionId' => $context->getDefinitionId(),
                ]);

                return;
            }

            $workflow = $this->findWorkflow($document, $context->getWorkflowId());
            if ($workflow === null) {
                $this->eventLedger->append($executionId, 'execution.workflow_missing', [
                    'workflowId' => $context->getWorkflowId(),
                ]);

                return;
            }

            $executor = $this->findExecutor($step, $document);
            if ($executor === null) {
                throw new LogicException("No StepProtocolExecutorInterface supports step '{$step->stepId}'.");
            }

            $outcome = $executor->execute($step, $context, $document, $executionId);

            if ($outcome->suspended) {
                $newContext = $context->withStepStatus($step->stepId, StepStatus::Suspended);
                $this->stateStore->save($executionId, $this->serialize($newContext), $this->stateTtlSeconds);
                $this->executionRegistry->start($executionId, $newContext->getDefinitionId(), $workflow->workflowId);
                $this->eventLedger->append($executionId, 'step.suspended', ['stepId' => $step->stepId]);

                return;
            }

            $contextWithResult = $context->withStepResult($step->stepId, [
                'statusCode' => $outcome->statusCode,
                'response' => ['statusCode' => $outcome->statusCode, 'body' => $outcome->responseBody],
                'outputs' => $outcome->outputs,
            ]);

            $criteriaMet = $this->expressionResolver->evaluateSuccessCriteria($step, $contextWithResult, $document);

            $this->stateStore->save($executionId, $this->serialize($contextWithResult), $this->stateTtlSeconds);
            $this->executionRegistry->start($executionId, $contextWithResult->getDefinitionId(), $workflow->workflowId);

            try {
                $this->eventLedger->append($executionId, 'step.executed', [
                    'stepId' => $step->stepId,
                    'statusCode' => $outcome->statusCode,
                    'outputs' => $outcome->outputs,
                    'criteriaMet' => $criteriaMet,
                ]);
            } catch (Throwable $e) {
                $this->logger?->warning("Failed to append event ledger entry for step '{$step->stepId}': {$e->getMessage()}");
            }

            $this->outcomeHandler->handle($document, $workflow, $step, $contextWithResult, $executionId, $criteriaMet);
        });
    }

    private function reconcileWithPersistedState(WorkflowContext $context, string $executionId): WorkflowContext
    {
        $persisted = $this->stateStore->load($executionId);
        if ($persisted === null) {
            return $context;
        }

        $mergedSteps = array_merge($persisted['steps'] ?? [], $context->getSteps());

        return new WorkflowContext(
            $context->getDefinitionId(),
            $context->getInputs(),
            $mergedSteps,
            $context->getComponents(),
            $context->getWorkflowId(),
            $executionId,
        );
    }

    private function findWorkflow(ArazzoDocument $document, ?string $workflowId): ?Workflow
    {
        foreach ($document->workflows as $workflow) {
            if ($workflow->workflowId === $workflowId) {
                return $workflow;
            }
        }

        return null;
    }

    private function findExecutor(Step $step, ArazzoDocument $document): ?StepProtocolExecutorInterface
    {
        foreach ($this->protocolExecutors as $executor) {
            if ($executor->supports($step, $document)) {
                return $executor;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(WorkflowContext $context): array
    {
        return [
            'definitionId' => $context->getDefinitionId(),
            'workflowId' => $context->getWorkflowId(),
            'steps' => $context->getSteps(),
            'inputs' => $context->getInputs(),
            'components' => $context->getComponents(),
        ];
    }
}
