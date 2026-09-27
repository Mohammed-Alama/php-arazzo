<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Contracts\Interfaces\LockStrategyInterface;
use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus;
use Alama\Arazzo\Contracts\Spec\Enum\StepStatus;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Engine\WorkflowEngineInterface;
use Alama\Arazzo\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runtime\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\StateStoreInterface;
use LogicException;

/**
 * Single carrier for both sync and async step execution.
 *
 * Replaces the split between WorkflowExecutor (sync) and StepExecutionWorker/
 * StepOutcomeHandler (async) with one canonical path. The only difference
 * between sync and async is the queue driver and lock manager injected.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class UnifiedStepCarrier
{
    /** @var list<OperationExecutorPluginInterface> */
    private array $executorPlugins;

    /**
     * @param  list<OperationExecutorPluginInterface>  $executorPlugins
     */
    public function __construct(
        private StateStoreInterface $stateStore,
        private WorkflowEngineInterface $workflowEngine,
        private LockStrategyInterface $lockManager,
        private ExecutionRegistryInterface $executionRegistry,
        private EventLedgerInterface $eventLedger,
        private PendingCorrelationRegistryInterface $pendingCorrelations,
        array $executorPlugins,
        private int $stateTtlSeconds = 86400,
    ) {
        // Sort by priority (lower = higher priority, first-match wins)
        $this->executorPlugins = $executorPlugins;
        usort($this->executorPlugins, static fn (OperationExecutorPluginInterface $a, OperationExecutorPluginInterface $b): int => $a->priority() <=> $b->priority());
    }

    public function execute(
        string $executionId,
        Step $step,
        Workflow $workflow,
        ArazzoDocument $document,
        ExecutionState $state,
    ): void {
        $this->lockManager->acquire("execution_lock_{$executionId}", 30, function () use ($executionId, $step, $workflow, $document, $state) {
            $this->executeUnderLock($executionId, $step, $workflow, $document, $state);
        });
    }

    private function executeUnderLock(
        string $executionId,
        Step $step,
        Workflow $workflow,
        ArazzoDocument $document,
        ExecutionState $state,
    ): void {
        $context = $state->toContext();
        $context = $context->withStepAttemptIncremented($step->stepId);
        $attempt = $context->getStepAttempts($step->stepId);

        $executor = $this->findExecutor($step, $document);
        if ($executor === null) {
            throw new LogicException("No OperationExecutorPluginInterface supports step '{$step->stepId}'.");
        }

        $outcome = $executor->execute($step, $context, $document, $executionId);

        if ($outcome->suspended) {
            $context = $context->withStepStatus($step->stepId, StepStatus::Suspended);
            $persistedArray = $context->toArray();
            $persistedArray['executionId'] = $executionId;
            $this->stateStore->save($executionId, $persistedArray, $this->stateTtlSeconds);
            $this->executionRegistry->start($executionId, $context->getDefinitionId(), $workflow->workflowId);
            $this->pendingCorrelations->create('', $executionId, $step->stepId, $step->target->channelPath ?? '', $this->stateTtlSeconds);

            return;
        }

        $contextWithResult = $context->withStepResult($step->stepId, [
            'statusCode' => $outcome->statusCode,
            'request' => $outcome->request ?? [],
            'response' => ['statusCode' => $outcome->statusCode, 'headers' => $outcome->responseHeaders, 'body' => $outcome->responseBody],
            'rawBody' => $outcome->rawBody,
            'contentType' => $outcome->contentType,
            'failureCategory' => $outcome->failureCategory,
            'outputs' => $outcome->outputs,
            'inputs' => $outcome->inputs,
            'attempts' => $attempt,
        ]);

        $persistedArray = $contextWithResult->toArray();
        $persistedArray['executionId'] = $executionId;
        $this->stateStore->save($executionId, $persistedArray, $this->stateTtlSeconds);

        $restoredState = ExecutionState::fromArray($this->stateStore->load($executionId) ?? $state->toArray());
        $transition = $this->workflowEngine->transition($document, $workflow, $step, $restoredState, $outcome->failureCategory === null);

        $nextState = $transition->state;

        $finalPersisted = $nextState->toContext()->toArray();
        $finalPersisted['executionId'] = $executionId;
        $this->stateStore->save($executionId, $finalPersisted, $this->stateTtlSeconds);

        $isTerminal = in_array($nextState->status, ['completed', 'failed', 'cancelled'], true);
        if ($isTerminal) {
            $succeeded = $transition->status === 'succeeded';
            $this->executionRegistry->complete($executionId, $succeeded ? ExecutionStatus::Succeeded : ExecutionStatus::Failed);
            $this->eventLedger->append($executionId, $succeeded ? 'execution.succeeded' : 'execution.failed', ['workflowId' => $transition->workflowId ?? $nextState->workflowId]);
        }
    }

    private function findExecutor(Step $step, ArazzoDocument $document): ?OperationExecutorPluginInterface
    {
        foreach ($this->executorPlugins as $plugin) {
            if ($plugin->supports($step, $document)) {
                return $plugin;
            }
        }

        return null;
    }
}
