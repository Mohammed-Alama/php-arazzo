<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution;

use Alama\Arazzo\Contracts\WorkflowContext;
use Alama\Arazzo\Events\RunCompleted;
use Alama\Arazzo\Events\RunFailed;
use Alama\Arazzo\Events\RunStarted;
use Alama\Arazzo\Events\StepExecuted as StepExecutedEvent;
use Alama\Arazzo\Events\StepFailed as StepFailedEvent;
use Alama\Arazzo\Events\StepRetried;
use Alama\Arazzo\Events\StepStarted;
use Alama\Arazzo\Exceptions\SchemaValidationException;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\State\ExecutionState;
use Alama\Arazzo\Support\Events\Dispatcher\NullEventDispatcher;
use Alama\Arazzo\Validator\PreflightFailureException;
use Alama\Arazzo\Validator\PreflightValidator;
use Alama\Arazzo\Validator\ValidationResult;
use DateTimeImmutable;
use LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Throwable;

class WorkflowExecutor
{
    private EventDispatcherInterface $events;

    public function __construct(
        private StepExecutor $stepExecutor,
        private WorkflowEngine $workflowEngine,
        ?EventDispatcherInterface $events = null,
        private ?PreflightValidator $preflight = null,
    ) {
        $this->events = $events ?? new NullEventDispatcher();
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public function execute(Workflow $workflow, ArazzoDocument $document, array $inputs, ?WorkflowContext $context = null): ExecutionResult
    {
        // Preflight runs before the first side effect (no events, no state).
        if ($this->preflight !== null) {
            $result = $this->preflight->validate($document);

            // Workflow `inputs` JSON-Schema pre-validation (first-mover:
            // no other Arazzo tool validates inputs before spending calls).
            $inputsSchema = $workflow->inputs;

            if (is_array($inputsSchema) && $inputsSchema !== []) {
                $inputResult = $this->preflight->validateInputs($document, $workflow->workflowId, $inputs);

                if ($inputResult->errors !== []) {
                    $result = new ValidationResult(
                        $document,
                        [...$result->errors, ...$inputResult->errors],
                        [...$result->warnings, ...$inputResult->warnings],
                    );
                }
            }

            if (!$result->isValid()) {
                throw new PreflightFailureException(
                    'Preflight validation failed with '.count($result->errors).' error(s).',
                    $result,
                );
            }
        }

        $executionId = is_string($inputs['__executionId'] ?? null) ? $inputs['__executionId'] : bin2hex(random_bytes(8));
        $context ??= new WorkflowContext($workflow->workflowId, $inputs);
        $context = $context->withWorkflowData($workflow->workflowId, ['inputs' => $inputs]);

        $this->events->dispatch(new RunStarted(
            $executionId,
            $workflow->workflowId,
            $workflow->workflowId,
            $inputs,
            new DateTimeImmutable(),
        ));

        return $this->executeCanonically($workflow, $document, $inputs, $context, $executionId);
    }

    /** @param array<string, mixed> $inputs */
    private function executeCanonically(Workflow $workflow, ArazzoDocument $document, array $inputs, ?WorkflowContext $context, string $executionId): ExecutionResult
    {
        // A caller-provided context may carry an inherited budget (nested
        // invocation): children continue from it instead of resetting.
        $state = ExecutionState::start(
            $executionId,
            $context?->getDefinitionId() ?? $workflow->workflowId,
            $workflow->workflowId,
            $inputs,
            components: $context?->getComponents() ?? [],
            stepsSpent: $context?->getStepsSpent() ?? 0,
            workflowCallStack: $context !== null && $context->getWorkflowCallStack() !== [] ? $context->getWorkflowCallStack() : null,
        );
        $currentWorkflow = $workflow;
        $stepId = $this->firstStep($workflow);
        $results = [];
        try {
            while ($stepId !== null) {
                $step = $this->step($currentWorkflow, $stepId);
                if ($step === null) {
                    throw new LogicException("Unknown step '{$stepId}' in workflow '{$currentWorkflow->workflowId}'.");
                }
                $attempt = $state->attemptFor($stepId) + 1;
                $this->events->dispatch(new StepStarted($executionId, $currentWorkflow->workflowId, $stepId, $attempt, new DateTimeImmutable()));
                [$stepContext, $success] = $this->stepExecutor->execute(StepParameterMerger::merge($step, $currentWorkflow), $state->toContext(), $document);
                $raw = $stepContext->getSteps()[$stepId] ?? [];
                /** @var array<string, mixed> $raw */
                $state = $state->withStepResult($stepId, $raw);
                $result = new StepResult(
                    $stepId,
                    $success,
                    is_array($raw['outputs'] ?? null) ? $raw['outputs'] : [],
                    $success ? null : new RuntimeException("Step '{$stepId}' failed"),
                );
                $results[$stepId] = $result;
                $transition = $this->workflowEngine->transition($document, $currentWorkflow, $step, $state, $success);
                $engineState = $transition->state;
                assert($engineState instanceof ExecutionState); // engine is canonical on ExecutionState
                $state = $engineState;
                if ($transition->type === TransitionType::Retry) {
                    $this->events->dispatch(new StepRetried($executionId, $currentWorkflow->workflowId, $stepId, $state->attemptFor($stepId), null, new DateTimeImmutable()));
                }
                if (!$success) {
                    $this->events->dispatch(new StepFailedEvent(
                        $executionId,
                        $currentWorkflow->workflowId,
                        $stepId,
                        new RuntimeException("Step '{$stepId}' failed"),
                        new DateTimeImmutable(),
                        is_string($raw['failureCategory'] ?? null) ? $raw['failureCategory'] : 'criteria',
                    ));
                } else {
                    $this->events->dispatch(new StepExecutedEvent($executionId, $currentWorkflow->workflowId, $stepId, (int) (is_scalar($raw['statusCode'] ?? null) ? $raw['statusCode'] : 0), $result->outputs, $success, new DateTimeImmutable()));
                }
                if ($transition->isTerminal()) {
                    if ($transition->status === 'failed') {
                        $this->events->dispatch(new RunFailed($executionId, $currentWorkflow->workflowId, new RuntimeException("Workflow '{$currentWorkflow->workflowId}' failed at step '{$stepId}'."), new DateTimeImmutable(), 'criteria'));

                        return new ExecutionResult($currentWorkflow->workflowId, 'failed', [], $results, $state->stepsSpent, $state->workflowCallStack);
                    }
                    $outputs = $this->workflowEngine->evaluateWorkflowOutputs($document, $currentWorkflow, $state);
                    $this->events->dispatch(new RunCompleted($executionId, $currentWorkflow->workflowId, $outputs, new DateTimeImmutable()));

                    return new ExecutionResult($currentWorkflow->workflowId, 'succeeded', $outputs, $results, $state->stepsSpent, $state->workflowCallStack);
                }
                if ($transition->workflowId !== null && $transition->workflowId !== $currentWorkflow->workflowId) {
                    $currentWorkflow = $this->workflow($document, $transition->workflowId);
                }
                $stepId = $transition->stepId ?? $this->firstStep($currentWorkflow);
            }
        } catch (Throwable $t) {
            $category = match (true) {
                $t instanceof PreflightFailureException => 'authoring',
                $t instanceof SchemaValidationException => 'schema',
                default => 'execution',
            };
            $this->events->dispatch(new RunFailed($executionId, $currentWorkflow->workflowId, $t, new DateTimeImmutable(), $category));
            throw $t;
        }

        return new ExecutionResult($currentWorkflow->workflowId, 'succeeded', $state->outputs, $results, $state->stepsSpent, $state->workflowCallStack);
    }

    private function firstStep(Workflow $workflow): ?string
    {
        return $workflow->steps[0]->stepId ?? null;
    }

    private function step(Workflow $workflow, string $id): ?Step
    {
        foreach ($workflow->steps as $step) {
            if ($step->stepId === $id) {
                return $step;
            }
        }

        return null;
    }

    private function workflow(ArazzoDocument $document, string $id): Workflow
    {
        foreach ($document->workflows as $workflow) {
            if ($workflow->workflowId === $id) {
                return $workflow;
            }
        } throw new LogicException("Unknown workflow '{$id}'.");
    }
}
