<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Events\CorrelationPending;
use Alama\Arazzo\Events\Dispatcher\NullEventDispatcher;
use Alama\Arazzo\Events\StepExecuted as StepExecutedEvent;
use Alama\Arazzo\Events\StepFailed as StepFailedEvent;
use Alama\Arazzo\Events\StepStarted;
use Alama\Arazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\Arazzo\Execution\Contracts\EventLedgerInterface;
use Alama\Arazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Execution\Contracts\LockManagerInterface;
use Alama\Arazzo\Execution\Contracts\StateStoreInterface;
use Alama\Arazzo\Execution\Contracts\StepProtocolExecutorInterface;
use Alama\Arazzo\Execution\Jobs\ExecuteStepJob;
use LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class StepExecutionWorker
{
    private EventDispatcherInterface $events;

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
        /** @phpstan-ignore property.onlyWritten */
        private ?LoggerInterface $logger = null,
        private int $stateTtlSeconds = 86400,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new NullEventDispatcher();
    }

    public function handle(ExecuteStepJob $job): void
    {
        $step = $job->step;
        $executionId = $job->context->getExecutionId();

        if ($executionId === null) {
            throw new LogicException(
                "ExecuteStepJob for step '{$step->stepId}' has no executionId -- the workflow run was not initialized before dispatch.",
            );
        }

        $lockKey = "execution_lock_{$executionId}";

        $this->lockManager->acquire($lockKey, 30, function () use ($step, $job, $executionId) {
            $context = $this->reconcileWithPersistedState($job->context, $executionId);

            try {
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

                $attempt = $context->getStepAttempts($step->stepId) + 1;
                $this->events->dispatch(new StepStarted(
                    $executionId,
                    $context->getWorkflowId() ?? '',
                    $step->stepId,
                    $attempt,
                    new \DateTimeImmutable(),
                ));

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

                    if ($step->action === 'receive' && $step->correlationId !== null && $step->channelPath !== null) {
                        $correlationIdValue = (string) $this->expressionResolver->evaluate($step->correlationId, $context, $step->stepId);
                        $this->events->dispatch(new CorrelationPending(
                            $executionId,
                            $context->getWorkflowId() ?? '',
                            $step->stepId,
                            $correlationIdValue,
                            $step->channelPath,
                            new \DateTimeImmutable(),
                        ));
                    }

                    return;
                }

                $contextWithResult = $context->withStepResult($step->stepId, [
                    'statusCode' => $outcome->statusCode,
                    'response' => ['statusCode' => $outcome->statusCode, 'body' => $outcome->responseBody],
                    'outputs' => $outcome->outputs,
                ]);

                $criteriaMet = $this->expressionResolver->evaluateSuccessCriteria($step, $contextWithResult, $document);

                $this->executionRegistry->start($executionId, $contextWithResult->getDefinitionId(), $workflow->workflowId);

                $this->outcomeHandler->handle($document, $workflow, $step, $contextWithResult, $executionId, $criteriaMet);

                $this->events->dispatch(new StepExecutedEvent(
                    $executionId,
                    $workflow->workflowId,
                    $step->stepId,
                    $outcome->statusCode ?? 0,
                    $outcome->outputs,
                    $criteriaMet,
                    new \DateTimeImmutable(),
                ));
            } catch (Throwable $t) {
                $this->events->dispatch(new StepFailedEvent(
                    $executionId,
                    $context->getWorkflowId() ?? '',
                    $step->stepId,
                    $t,
                    new \DateTimeImmutable(),
                ));
                throw $t;
            }
        });
    }

    private function reconcileWithPersistedState(WorkflowContext $context, string $executionId): WorkflowContext
    {
        $persisted = $this->stateStore->load($executionId);
        if ($persisted === null) {
            return $context;
        }

        $mergedSteps = array_merge($context->getSteps(), $persisted['steps'] ?? []);

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
