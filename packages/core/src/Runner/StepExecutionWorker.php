<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Events\CorrelationPending;
use Alama\Arazzo\Events\Dispatcher\NullEventDispatcher;
use Alama\Arazzo\Events\StepExecuted as StepExecutedEvent;
use Alama\Arazzo\Events\StepFailed as StepFailedEvent;
use Alama\Arazzo\Events\StepStarted;
use Alama\Arazzo\Runner\Contracts\DefinitionRegistryInterface;
use Alama\Arazzo\Runner\Contracts\EventLedgerInterface;
use Alama\Arazzo\Runner\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Contracts\LockManagerInterface;
use Alama\Arazzo\Runner\Contracts\QueueDriverInterface;
use Alama\Arazzo\Runner\Contracts\StateStoreInterface;
use Alama\Arazzo\Runner\Contracts\StepProtocolExecutorInterface;
use Alama\Arazzo\Runner\Dto\ExecutionState;
use Alama\Arazzo\Runner\Jobs\ExecuteStepJob;
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
        private ?WorkflowEngine $workflowEngine = null,
        private ?QueueDriverInterface $queueDriver = null,
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

                if ($this->workflowEngine !== null) {
                    $persisted = $this->stateStore->load($executionId);
                    $state = $persisted !== null && isset($persisted['executionId'])
                        ? ExecutionState::fromArray($persisted)
                        : ExecutionState::start($executionId, $contextWithResult->getDefinitionId(), $workflow->workflowId, $contextWithResult->getInputs(), components: $contextWithResult->getComponents());
                    foreach ($contextWithResult->getSteps() as $completedStepId => $completedResult) {
                        $state = $state->withStepResult($completedStepId, $completedResult);
                    }
                    foreach ($this->attemptsFrom($contextWithResult) as $attemptedStepId => $attempts) {
                        while ($state->attemptFor($attemptedStepId) < $attempts) {
                            $state = $state->withStepAttempt($attemptedStepId);
                        }
                    }
                    $state = $state->withStepResult($step->stepId, $contextWithResult->getSteps()[$step->stepId] ?? []);
                    $transition = $this->workflowEngine->transition($document, $workflow, $step, $state, $criteriaMet);
                    $this->stateStore->save($executionId, $transition->state->toArray(), $this->stateTtlSeconds);
                    if ($transition->isTerminal()) {
                        $this->executionRegistry->complete($executionId, $transition->status === 'succeeded' ? ExecutionStatus::Succeeded : ExecutionStatus::Failed);
                        $this->eventLedger->append($executionId, $transition->status === 'succeeded' ? 'execution.succeeded' : 'execution.failed', ['workflowId' => $transition->state->workflowId]);
                    } elseif ($this->queueDriver !== null && $transition->type !== 'suspend') {
                        $targetWorkflow = $this->findWorkflow($document, $transition->workflowId ?? $workflow->workflowId) ?? $workflow;
                        $targetStep = $this->findStep($targetWorkflow, $transition->stepId ?? '');
                        if ($targetStep !== null) {
                            $this->queueDriver->dispatch(new ExecuteStepJob($targetStep, $this->contextFromState($transition->state)), $transition->delaySeconds);
                        }
                    }
                } else {
                    $this->outcomeHandler->handle($document, $workflow, $step, $contextWithResult, $executionId, $criteriaMet);
                }

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

    private function findStep(Workflow $workflow, string $stepId): ?Step
    {
        foreach ($workflow->steps as $step) {
            if ($step->stepId === $stepId) {
                return $step;
            }
        }

        return null;
    }

    private function contextFromState(ExecutionState $state): WorkflowContext
    {
        return new WorkflowContext($state->definitionId, $state->inputs, $state->stepResults, $state->components, $state->workflowId, $state->executionId);
    }

    /** @return array<string, int> */
    private function attemptsFrom(WorkflowContext $context): array
    {
        $attempts = [];
        foreach ($context->getSteps() as $id => $step) {
            $attempts[$id] = (int) ($step['attempts'] ?? 0);
        }

        return $attempts;
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
