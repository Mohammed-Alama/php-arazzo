<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Runner\Context\Contracts\StateStoreInterface;
use Alama\Arazzo\Runner\Context\ExecutionState;
use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Events\CorrelationPending;
use Alama\Arazzo\Runner\Events\RunCompleted;
use Alama\Arazzo\Runner\Events\RunFailed;
use Alama\Arazzo\Runner\Events\StepExecuted as StepExecutedEvent;
use Alama\Arazzo\Runner\Events\StepFailed as StepFailedEvent;
use Alama\Arazzo\Runner\Events\StepStarted;
use Alama\Arazzo\Runner\Exceptions\SchemaValidationException;
use Alama\Arazzo\Runner\Execution\Contracts\DefinitionRegistryInterface;
use Alama\Arazzo\Runner\Execution\Contracts\EventLedgerInterface;
use Alama\Arazzo\Runner\Execution\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\Execution\Contracts\LockManagerInterface;
use Alama\Arazzo\Runner\Execution\Contracts\QueueDriverInterface;
use Alama\Arazzo\Runner\Execution\Contracts\StepProtocolExecutorInterface;
use Alama\Arazzo\Runner\Execution\Enum\TransitionType;
use Alama\Arazzo\Runner\Jobs\ExecuteStepJob;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Support\Events\Dispatcher\NullEventDispatcher;
use DateTimeImmutable;
use LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
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
        private WorkflowEngine $workflowEngine,
        private QueueDriverInterface $queueDriver,
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

                // Record the attempt on the persisted context so retry
                // ceilings survive job boundaries (parity with the sync loop).
                $context = $context->withStepAttemptIncremented($step->stepId);
                $attempt = $context->getStepAttempts($step->stepId);
                $this->events->dispatch(new StepStarted(
                    $executionId,
                    $context->getWorkflowId() ?? '',
                    $step->stepId,
                    $attempt,
                    new DateTimeImmutable(),
                ));

                $step = StepParameterMerger::merge($step, $workflow);

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
                            new DateTimeImmutable(),
                        ));
                    }

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

                $criteriaMet = $this->expressionResolver->evaluateSuccessCriteria($step, $contextWithResult, $document);

                $this->executionRegistry->start($executionId, $contextWithResult->getDefinitionId(), $workflow->workflowId);

                $persisted = $this->stateStore->load($executionId);
                $state = $persisted !== null && isset($persisted['executionId'])
                    ? ExecutionState::fromArray($persisted)
                    : ExecutionState::start($executionId, $contextWithResult->getDefinitionId(), $workflow->workflowId, $contextWithResult->getInputs(), components: $contextWithResult->getComponents());
                foreach ($contextWithResult->getSteps() as $completedStepId => $completedResult) {
                    $state = $state->withStepResult($completedStepId, $completedResult);
                }
                foreach ($this->attemptsFrom($contextWithResult) as $attemptedStepId => $attempts) {
                    // Persisted 'attempts' is the 1-based number of the attempt
                    // that just ran; at decision time the engine must see the
                    // count of PREVIOUS attempts, matching the sync loop.
                    while ($state->attemptFor($attemptedStepId) < $attempts - 1) {
                        $state = $state->withStepAttempt($attemptedStepId);
                    }
                }
                $state = $state->withStepResult($step->stepId, $contextWithResult->getSteps()[$step->stepId] ?? []);
                $transition = $this->workflowEngine->transition($document, $workflow, $step, $state, $criteriaMet);
                // Persist in WorkflowContext shape so reconcileWithPersistedState
                // and CorrelationResumer keep reading the same keys.
                $this->stateStore->save($executionId, $this->serialize($this->contextFromState($transition->state)), $this->stateTtlSeconds);

                if ($transition->isTerminal()) {
                    $succeeded = $transition->status === 'succeeded';
                    $this->executionRegistry->complete($executionId, $succeeded ? ExecutionStatus::Succeeded : ExecutionStatus::Failed);
                    $this->eventLedger->append($executionId, $succeeded ? 'execution.succeeded' : 'execution.failed', ['workflowId' => $transition->state->workflowId]);

                    if ($succeeded) {
                        $this->events->dispatch(new RunCompleted(
                            $executionId,
                            $transition->state->workflowId,
                            $this->workflowEngine->evaluateWorkflowOutputs($document, $workflow, $transition->state),
                            new DateTimeImmutable(),
                        ));
                    } else {
                        $this->events->dispatch(new RunFailed(
                            $executionId,
                            $transition->state->workflowId,
                            new RuntimeException("Workflow '{$transition->state->workflowId}' failed at step '{$step->stepId}'."),
                            new DateTimeImmutable(),
                        ));
                    }
                } elseif ($transition->type !== TransitionType::Suspend) {
                    $targetWorkflowId = $transition->workflowId ?? $workflow->workflowId;
                    $targetWorkflow = $this->findWorkflow($document, $targetWorkflowId);

                    if ($targetWorkflow === null) {
                        // An invoke/goto pointed at a workflow that does not
                        // exist in the document: fail the run cleanly.
                        $this->eventLedger->append($executionId, 'execution.workflow_missing', ['workflowId' => $targetWorkflowId]);
                        $this->events->dispatch(new RunFailed(
                            $executionId,
                            $targetWorkflowId,
                            new LogicException("Unknown workflow '{$targetWorkflowId}'."),
                            new DateTimeImmutable(),
                        ));

                        return;
                    }

                    $targetStep = $transition->stepId !== null
                        ? $this->findStep($targetWorkflow, $transition->stepId)
                        : ($targetWorkflow->steps[0] ?? null);

                    if ($targetStep !== null) {
                        $this->queueDriver->dispatch(new ExecuteStepJob($targetStep, $this->contextFromState($transition->state)), $transition->delaySeconds);
                    }
                }

                $this->events->dispatch(new StepExecutedEvent(
                    $executionId,
                    $workflow->workflowId,
                    $step->stepId,
                    $outcome->statusCode ?? 0,
                    $outcome->outputs,
                    $criteriaMet,
                    new DateTimeImmutable(),
                ));
            } catch (Throwable $t) {
                $category = match (true) {
                    $t instanceof SchemaValidationException => 'schema',
                    default => 'execution',
                };
                $this->events->dispatch(new StepFailedEvent(
                    $executionId,
                    $context->getWorkflowId() ?? '',
                    $step->stepId,
                    $t,
                    new DateTimeImmutable(),
                    $category,
                ));
                $this->events->dispatch(new RunFailed(
                    $executionId,
                    $context->getWorkflowId() ?? '',
                    $t,
                    new DateTimeImmutable(),
                    $category,
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

    /** @return array<string, int> */
    private function attemptsFrom(WorkflowContext $context): array
    {
        $attempts = [];
        foreach ($context->getSteps() as $id => $step) {
            $attempts[$id] = (int) ($step['attempts'] ?? 0);
        }

        return $attempts;
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
}
