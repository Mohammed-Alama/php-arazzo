<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution;

use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Events\CorrelationPendingEvent;
use Alama\Arazzo\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Events\RunCompletedEvent;
use Alama\Arazzo\Events\RunFailedEvent;
use Alama\Arazzo\Events\StepExecutedEvent;
use Alama\Arazzo\Events\StepFailedEvent;
use Alama\Arazzo\Events\StepStartedEvent;
use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Execution\Data\RunControlFlow;
use Alama\Arazzo\Execution\Data\RunPersistence;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Execution\Enum\TransitionType;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Jobs\ExecuteStepJob;
use Alama\Arazzo\Contracts\Interfaces\StepProtocolExecutorInterface;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\ExecutionStatus;
use Alama\Arazzo\Spec\Enum\StepStatus;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\State\Interfaces\StateStoreInterface;
use Alama\Arazzo\Support\Events\Dispatcher\NullEventDispatcher;
use Alama\Arazzo\Telemetry\OtelSetup;
use Alama\Arazzo\Validator\Exceptions\PreflightFailureException;
use Alama\Arazzo\Validator\Exceptions\SchemaValidationException;
use Alama\Arazzo\Validator\PreflightValidator;
use DateTimeImmutable;
use LogicException;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Throwable;

class StepExecutionWorker
{
    private EventDispatcherInterface $events;

    private ?PreflightValidator $preflight;

    private StateStoreInterface $stateStore;

    private EventLedgerInterface $eventLedger;

    private ExecutionRegistryInterface $executionRegistry;

    private WorkflowEngine $workflowEngine;

    private QueueDriverInterface $queueDriver;

    /**
     * @param  list<StepProtocolExecutorInterface>  $protocolExecutors
     */
    public function __construct(
        RunPersistence $persistence,
        private LockManagerInterface $lockManager,
        private DefinitionRegistryInterface $definitionRegistry,
        private ExpressionResolverInterface $expressionResolver,
        private array $protocolExecutors,
        RunControlFlow $controlFlow,
        private int $stateTtlSeconds = 86400,
    ) {
        $this->stateStore = $persistence->stateStore;
        $this->eventLedger = $persistence->eventLedger;
        $this->executionRegistry = $persistence->executionRegistry;
        $this->workflowEngine = $controlFlow->workflowEngine;
        $this->queueDriver = $controlFlow->queueDriver;
        $this->preflight = $controlFlow->preflight;
        $this->events = $controlFlow->events ?? new NullEventDispatcher();
    }

    public function handle(ExecuteStepJob $job): void
    {
        $step = $job->step;
        $executionId = $job->context->getExecutionId();

        $span = OtelSetup::getTracer()->spanBuilder('arazzo.step.execute')
            ->setAttribute('execution.id', $executionId)
            ->setAttribute('workflow.id', (string) $job->context->getWorkflowId())
            ->setAttribute('step.id', $step->stepId)
            ->startSpan();

        $scope = $span->activate();

        try {
            $this->handleUnderSpan($job, $span);
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    private function handleUnderSpan(ExecuteStepJob $job, SpanInterface $span): void
    {
        $step = $job->step;
        $executionId = $job->context->getExecutionId();

        $lockKey = "execution_lock_{$executionId}";

        $this->lockManager->acquire($lockKey, 30, function () use ($step, $job, $executionId, $span) {
            $context = $this->reconcileWithPersistedState($job->context, $executionId);

            try {
                $this->documentPreflightValidation($context);

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
                $this->events->dispatch(new StepStartedEvent(
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
                        $this->events->dispatch(new CorrelationPendingEvent(
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
                $state = $state->restoreBudget($contextWithResult->getStepsSpent(), $contextWithResult->getWorkflowCallStack() ?: [$workflow->workflowId]);
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
                $nextState = $transition->state;
                assert($nextState instanceof ExecutionState); // engine is canonical on ExecutionState
                $this->stateStore->save($executionId, $this->serialize($this->contextFromState($nextState)), $this->stateTtlSeconds);

                $span->setAttribute('criteria.met', $criteriaMet);

                if ($transition->isTerminal()) {
                    $succeeded = $transition->status === 'succeeded';
                    $span->setStatus($succeeded
                        ? StatusCode::STATUS_OK
                        : StatusCode::STATUS_ERROR, "Step '{$step->stepId}' failed criteria");
                    $this->executionRegistry->complete($executionId, $succeeded ? ExecutionStatus::Succeeded : ExecutionStatus::Failed);
                    $this->eventLedger->append($executionId, $succeeded ? 'execution.succeeded' : 'execution.failed', ['workflowId' => $transition->state->workflowId]);

                    if ($succeeded) {
                        $this->events->dispatch(new RunCompletedEvent(
                            $executionId,
                            $transition->state->workflowId,
                            $this->workflowEngine->evaluateWorkflowOutputs($document, $workflow, $transition->state),
                            new DateTimeImmutable(),
                        ));
                    } else {
                        $this->events->dispatch(new RunFailedEvent(
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
                        $this->events->dispatch(new RunFailedEvent(
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
                        $dispatchState = $transition->state;
                        assert($dispatchState instanceof ExecutionState); // engine is canonical on ExecutionState
                        $this->queueDriver->dispatch(new ExecuteStepJob($targetStep, $this->contextFromState($dispatchState)), $transition->delaySeconds);
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
                $span->recordException($t);
                $span->setStatus(StatusCode::STATUS_ERROR, $t->getMessage());
                $category = match (true) {
                    $t instanceof PreflightFailureException => 'authoring',
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
                $this->events->dispatch(new RunFailedEvent(
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

        return $persisted === null
            ? $context
            : WorkflowContext::reconciled($context, $persisted, $executionId);
    }

    private function findWorkflow(ArazzoDocument $document, ?string $workflowId): ?Workflow
    {
        return array_find($document->workflows, fn ($workflow) => $workflow->workflowId === $workflowId);
    }

    private function findExecutor(Step $step, ArazzoDocument $document): ?StepProtocolExecutorInterface
    {
        return array_find($this->protocolExecutors, fn ($executor) => $executor->supports($step, $document));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(WorkflowContext $context): array
    {
        return $context->toArray();
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
        return array_find($workflow->steps, fn ($step) => $step->stepId === $stepId);
    }

    private function contextFromState(ExecutionState $state): WorkflowContext
    {
        return $state->toContext();
    }

    private function documentPreflightValidation(WorkflowContext $context): void
    {
        // Preflight only guards a FRESH run; resumed jobs already passed it.
        if ($this->preflight !== null && $context->getSteps() === []) {
            $documentForPreflight = $this->definitionRegistry->get($context->getDefinitionId());
            if ($documentForPreflight !== null) {
                $preflightResult = $this->preflight->validate($documentForPreflight);
                if (!$preflightResult->isValid()) {
                    throw new PreflightFailureException(
                        'Preflight validation failed with '.count($preflightResult->errors).' error(s).',
                        $preflightResult,
                    );
                }
            }
        }
    }
}
