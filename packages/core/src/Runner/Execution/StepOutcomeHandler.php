<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Runner\Context\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\Context\Contracts\StateStoreInterface;
use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Evaluation\DependencyAnalyzer;
use Alama\Arazzo\Runner\Evaluation\DependencyGraph;
use Alama\Arazzo\Runner\Evaluation\EvaluationContext;
use Alama\Arazzo\Runner\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Runner\Evaluation\SelectorEvaluator;
use Alama\Arazzo\Runner\Events\RunCompleted;
use Alama\Arazzo\Runner\Events\RunFailed;
use Alama\Arazzo\Runner\Events\StepRetried;
use Alama\Arazzo\Runner\Exceptions\GotoTargetNotFoundException;
use Alama\Arazzo\Runner\Execution\Contracts\EventLedgerInterface;
use Alama\Arazzo\Runner\Execution\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\Execution\Contracts\QueueDriverInterface;
use Alama\Arazzo\Runner\Jobs\ExecuteStepJob;
use Alama\Arazzo\Spec\Action\FailureAction;
use Alama\Arazzo\Spec\Action\FailureEndAction;
use Alama\Arazzo\Spec\Action\FailureGotoAction;
use Alama\Arazzo\Spec\Action\RetryAction;
use Alama\Arazzo\Spec\Action\SubWorkflowFailureAction;
use Alama\Arazzo\Spec\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Spec\Action\SuccessAction;
use Alama\Arazzo\Spec\Action\SuccessEndAction;
use Alama\Arazzo\Spec\Action\SuccessGotoAction;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Reusable;
use Alama\Arazzo\Spec\Selector;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Support\Events\Dispatcher\NullEventDispatcher;
use DateTimeImmutable;
use LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

class StepOutcomeHandler
{
    private EventDispatcherInterface $events;

    public function __construct(
        private QueueDriverInterface $queueDriver,
        private Engine $engine,
        private ExecutionRegistryInterface $executionRegistry,
        private EventLedgerInterface $eventLedger,
        private PendingCorrelationRegistryInterface $pendingCorrelations,
        private ExpressionResolverInterface $expressionResolver,
        private StateStoreInterface $stateStore,
        private SubWorkflowInvoker $invoker,
        private SelectorEvaluator $selectors,
        private ExpressionEvaluator $expressions,
        private int $maxRetryAttempts = 10,
        private int $stateTtlSeconds = 86400,
        ?EventDispatcherInterface $events = null,
        private float $retryBackoffMultiplier = 1.0,
    ) {
        $this->events = $events ?? new NullEventDispatcher();
    }

    public function handle(
        ArazzoDocument $document,
        Workflow $workflow,
        Step $step,
        WorkflowContext $context,
        string $executionId,
        bool $criteriaMet,
    ): void {
        foreach ($step->outputs as $name => $value) {
            $resolved = match (true) {
                $value instanceof Selector => $this->selectors->evaluate($value, $context, $step->stepId),
                $value instanceof Expression => $this->expressions->evaluate($value, new EvaluationContext($context, $step->stepId)),
                default => $value,
            };
            $context = $context->withStepOutput($step->stepId, $name, $resolved);
        }

        $actions = $this->resolveActionList($document, $workflow, $step, $criteriaMet);

        $this->applyFirstMatch($actions, $document, $workflow, $step, $context, $executionId, $criteriaMet);
    }

    /**
     * @return list<SuccessAction|FailureAction>
     */
    private function resolveActionList(ArazzoDocument $document, Workflow $workflow, Step $step, bool $criteriaMet): array
    {
        $stepList = $criteriaMet ? $step->onSuccess : $step->onFailure;
        $list = $stepList !== [] ? $stepList : ($criteriaMet ? $workflow->successActions : $workflow->failureActions);
        $componentType = $criteriaMet ? 'successActions' : 'failureActions';

        return array_map(fn ($action) => $this->resolveReusable($action, $document, $componentType), $list);
    }

    private function resolveReusable(SuccessAction|FailureAction|Reusable $action, ArazzoDocument $document, string $componentType): SuccessAction|FailureAction
    {
        if (!$action instanceof Reusable) {
            return $action;
        }

        $prefix = "\$components.{$componentType}.";
        if (!str_starts_with($action->reference, $prefix)) {
            throw new GotoTargetNotFoundException("Reusable reference '{$action->reference}' does not target components.{$componentType}.");
        }

        $name = substr($action->reference, strlen($prefix));
        $resolved = $componentType === 'successActions'
            ? ($document->components->successActions[$name] ?? null)
            : ($document->components->failureActions[$name] ?? null);

        if ($resolved === null) {
            throw new GotoTargetNotFoundException("Reusable reference '{$action->reference}' does not resolve.");
        }

        return $resolved;
    }

    /**
     * @param list<SuccessAction|FailureAction> $actions
     */
    private function applyFirstMatch(
        array $actions,
        ArazzoDocument $document,
        Workflow $workflow,
        Step $step,
        WorkflowContext $context,
        string $executionId,
        bool $criteriaMet,
    ): void {
        $matched = $this->firstMatchingAction($actions, $step, $context, $document);

        if ($matched === null) {
            if ($criteriaMet) {
                $this->continueNormally($workflow, $step, $context, $executionId);
            } else {
                $this->terminate($context, $executionId, ExecutionStatus::Failed, 'execution.failed');
            }

            return;
        }

        if ($matched instanceof RetryAction) {
            $this->handleRetry($matched, $actions, $document, $workflow, $step, $context, $executionId, $criteriaMet);

            return;
        }

        if ($matched instanceof SuccessGotoAction || $matched instanceof FailureGotoAction) {
            $status = $matched instanceof SuccessGotoAction ? StepStatus::Succeeded : StepStatus::Failed;
            $newContext = $context->withStepStatus($step->stepId, $status);
            $this->handleGoto($matched, $step, $document, $newContext, $executionId);

            return;
        }

        if ($matched instanceof SuccessEndAction || $matched instanceof FailureEndAction) {
            $status = $matched instanceof SuccessEndAction ? ExecutionStatus::Succeeded : ExecutionStatus::Failed;
            $this->terminate(
                $context,
                $executionId,
                $status,
                $status === ExecutionStatus::Succeeded ? 'execution.succeeded' : 'execution.failed',
            );

            if ($matched instanceof SuccessEndAction) {
                $this->events->dispatch(new RunCompleted(
                    $executionId,
                    $workflow->workflowId,
                    $context->getSteps()[$step->stepId]['outputs'] ?? [],
                    new DateTimeImmutable(),
                ));
            } else {
                $this->events->dispatch(new RunFailed(
                    $executionId,
                    $workflow->workflowId,
                    new RuntimeException("Workflow '{$workflow->workflowId}' ended in failure at step '{$step->stepId}'"),
                    new DateTimeImmutable(),
                ));
            }

            return;
        }

        if ($matched instanceof SubWorkflowSuccessAction || $matched instanceof SubWorkflowFailureAction) {
            $result = $this->invoker->invoke($matched, $context);
            $context = $context->withWorkflowData($matched->workflowId, ['inputs' => $result->inputs, 'outputs' => $result->outputs]);
            $context = $context->withStepOutput($step->stepId, $matched->name, $result->outputs);
            $this->continueNormally($workflow, $step, $context, $executionId);

            return;
        }

        throw new LogicException('Unhandled action type: ' . $matched::class);
    }

    /**
     * @param list<SuccessAction|FailureAction> $actions
     */
    private function firstMatchingAction(array $actions, Step $step, WorkflowContext $context, ArazzoDocument $document): SuccessAction|FailureAction|null
    {
        foreach ($actions as $action) {
            if ($this->expressionResolver->evaluateCriteria($action->criteria, $step, $context, $document)) {
                return $action;
            }
        }

        return null;
    }

    private function continueNormally(Workflow $workflow, Step $step, WorkflowContext $context, string $executionId): void
    {
        $newContext = $context->withStepStatus($step->stepId, StepStatus::Succeeded);

        $this->stateStore->save($executionId, $this->serialize($newContext), $this->stateTtlSeconds);
        $this->engine->evaluate($workflow, $newContext);

        $graph = new DependencyGraph($workflow->steps);
        $analyzer = new DependencyAnalyzer($graph);
        $runnable = $analyzer->getRunnableSteps($newContext);
        if ($runnable === [] && !$this->pendingCorrelations->existsForExecution($executionId)) {
            $this->terminate($newContext, $executionId, ExecutionStatus::Succeeded, 'execution.succeeded');
        }
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

    private function terminate(WorkflowContext $context, string $executionId, ExecutionStatus $status, string $eventType): void
    {
        $this->stateStore->save($executionId, $this->serialize($context), $this->stateTtlSeconds);
        $this->executionRegistry->complete($executionId, $status);
        $this->eventLedger->append($executionId, $eventType, ['workflowId' => $context->getWorkflowId()]);
    }

    /**
     * @param list<SuccessAction|FailureAction> $actionsConsidered
     */
    private function handleRetry(
        RetryAction $action,
        array $actionsConsidered,
        ArazzoDocument $document,
        Workflow $workflow,
        Step $step,
        WorkflowContext $context,
        string $executionId,
        bool $criteriaMet,
    ): void {
        $attempts = $context->getStepAttempts($step->stepId);
        $limit = min($action->retryLimit ?? PHP_INT_MAX, $this->maxRetryAttempts);

        if ($attempts >= $limit) {
            $this->eventLedger->append($executionId, 'step.retry_exhausted', [
                'stepId' => $step->stepId,
                'attempts' => $attempts,
            ]);

            $index = array_search($action, $actionsConsidered, true);
            $remaining = array_slice($actionsConsidered, $index + 1);
            $this->applyFirstMatch($remaining, $document, $workflow, $step, $context, $executionId, $criteriaMet);

            return;
        }

        $targetStepId = $action->stepId ?? $step->stepId;
        $targetWorkflowId = $action->workflowId ?? $workflow->workflowId;
        [$targetWorkflow, $targetStep] = $this->resolveTarget($document, $targetWorkflowId, $targetStepId);

        $newContext = $context
            ->withStepAttemptIncremented($step->stepId)
            ->withStepStatus($step->stepId, StepStatus::Retrying);

        if ($targetWorkflow->workflowId !== $context->getWorkflowId()) {
            $newContext = $newContext->withWorkflowId($targetWorkflow->workflowId);
        }
        if ($targetStep->stepId !== $step->stepId) {
            $newContext = $newContext->withStepStatus($targetStep->stepId, StepStatus::Pending);
        }

        $this->stateStore->save($executionId, $this->serialize($newContext), $this->stateTtlSeconds);

        $attempt = $context->getStepAttempts($step->stepId);
        $this->events->dispatch(new StepRetried(
            $executionId,
            $workflow->workflowId,
            $step->stepId,
            $attempt,
            null,
            new DateTimeImmutable(),
        ));

        $this->queueDriver->dispatch(new ExecuteStepJob($targetStep, $newContext), $this->retryDelaySeconds($action, $step, $context, $attempt + 1));
    }

    /**
     * Resolves the retry delay in whole seconds. The HTTP Retry-After header
     * overrules the declared retryAfter when parseable; otherwise the declared
     * delay is scaled by the configured backoff multiplier per attempt number
     * (the upcoming attempt, 1-based).
     */
    private function retryDelaySeconds(RetryAction $action, Step $step, WorkflowContext $context, int $upcomingAttempt): int
    {
        $headerValue = self::lookupHeader($context, $step->stepId, 'Retry-After');

        if ($headerValue !== null) {
            if (preg_match('/^\d+$/', trim($headerValue)) === 1) {
                return max(0, (int) trim($headerValue));
            }

            $date = DateTimeImmutable::createFromFormat(DATE_RFC7231, trim($headerValue));
            if ($date !== false) {
                return max(0, $date->getTimestamp() - time());
            }
        }

        $base = $action->retryAfter ?? 0;
        $scaled = $base * ($this->retryBackoffMultiplier ** max(0, $upcomingAttempt - 1));

        return max(0, (int) ceil($scaled));
    }

    /**
     * Case-insensitive header lookup against the current step's recorded response.
     */
    private static function lookupHeader(WorkflowContext $context, string $stepId, string $name): ?string
    {
        $steps = $context->getSteps();
        $stepData = $steps[$stepId] ?? null;
        $response = is_array($stepData) ? ($stepData['response'] ?? null) : null;
        if (!is_array($response)) {
            return null;
        }

        $headers = $response['headers'] ?? [];
        if (!is_array($headers)) {
            return null;
        }

        foreach ($headers as $key => $value) {
            if (is_string($key) && strcasecmp($key, $name) === 0 && is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @return array{0: Workflow, 1: Step}
     */
    private function resolveTarget(ArazzoDocument $document, string $workflowId, string $stepId): array
    {
        $workflow = $this->findWorkflow($document, $workflowId);
        if ($workflow === null) {
            throw new GotoTargetNotFoundException("Action references unknown workflowId '{$workflowId}'.");
        }

        $step = $this->findStep($workflow, $stepId);
        if ($step === null) {
            throw new GotoTargetNotFoundException("Action references unknown stepId '{$stepId}' in workflow '{$workflow->workflowId}'.");
        }

        return [$workflow, $step];
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

    private function handleGoto(SuccessGotoAction|FailureGotoAction $action, Step $step, ArazzoDocument $document, WorkflowContext $context, string $executionId): void
    {
        $targetWorkflowId = $action->workflowId ?? $context->getWorkflowId();
        $targetWorkflow = $this->findWorkflow($document, (string) $targetWorkflowId);
        if ($targetWorkflow === null) {
            throw new GotoTargetNotFoundException("Goto action '{$action->name}' references unknown workflowId '{$targetWorkflowId}'.");
        }

        $newContext = $targetWorkflow->workflowId !== $context->getWorkflowId()
            ? $context->withWorkflowId($targetWorkflow->workflowId)
            : $context;

        // 1.1: goto parameters bind values into the target workflow's input scope.
        if ($action->parameters !== []) {
            $evaluationContext = new EvaluationContext($context, $step->stepId, $document);
            $bound = [];
            foreach ($action->parameters as $parameter) {
                if ($parameter instanceof Reusable) {
                    continue; // component defaults resolve inside the target scope
                }

                $bound[$parameter->name] = $parameter->value instanceof Expression
                    ? $this->expressions->evaluate($parameter->value, $evaluationContext)
                    : $parameter->value;
            }

            foreach ($bound as $k => $v) {
                $newContext = $newContext->withInput($k, $v);
            }
        }

        if ($action->stepId === null) {
            // No specific step named -- transfer to the target workflow's start, letting
            // normal dependency-driven choreography pick its entry steps.
            $this->stateStore->save($executionId, $this->serialize($newContext), $this->stateTtlSeconds);
            $this->engine->evaluate($targetWorkflow, $newContext);

            return;
        }

        $targetStep = $this->findStep($targetWorkflow, $action->stepId);
        if ($targetStep === null) {
            throw new GotoTargetNotFoundException("Goto action '{$action->name}' references unknown stepId '{$action->stepId}' in workflow '{$targetWorkflow->workflowId}'.");
        }

        $newContext = $newContext->withStepStatus($targetStep->stepId, StepStatus::Pending);

        $this->stateStore->save($executionId, $this->serialize($newContext), $this->stateTtlSeconds);
        $this->queueDriver->dispatch(new ExecuteStepJob($targetStep, $newContext));
    }
}
