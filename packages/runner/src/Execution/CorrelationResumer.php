<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Contracts\Support\Events\Dispatcher\NullEventDispatcher;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Events\CorrelationResumedEvent;
use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\StateStoreInterface;
use DateTimeImmutable;
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
     * @param  array{statusCode?: int, headers?: array<string, mixed>, body?: mixed}  $response
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

            // Step timeout enforcement for receive steps: an expired correlation is
            // consumed and routed through the failure path with a synthesized 504,
            // so onFailure actions (retry/goto/end) apply normally.
            if ($correlation->expiresAt !== null && new DateTimeImmutable() > $correlation->expiresAt) {
                $this->pendingCorrelations->consume($correlationId);
                $this->eventLedger->append($executionId, 'step.correlation_expired', [
                    'stepId' => $correlation->stepId,
                    'correlationId' => $correlationId,
                    'expiresAt' => $correlation->expiresAt->format(DATE_ATOM),
                ]);

                $timedOut = $this->hydrateContext($persisted, $executionId)
                    ->withStepResponse($step->stepId, [
                        'statusCode' => 504,
                        'headers' => [],
                        'body' => [],
                    ]);

                $this->outcomeHandler->handle($document, $workflow, $step, $timedOut, $executionId, false);

                return;
            }

            $context = $this->hydrateContext($persisted, $executionId);

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

            $this->events->dispatch(new CorrelationResumedEvent(
                $executionId, $workflow->workflowId, $step->stepId, $correlationId, new DateTimeImmutable(),
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

    /**
     * @param  array<string, mixed>  $persisted
     */
    private function hydrateContext(array $persisted, string $executionId): WorkflowContext
    {
        return WorkflowContext::fromPersisted($persisted, $executionId);
    }

    private function findWorkflow(ArazzoDocument $document, string $workflowId): ?Workflow
    {
        return array_find($document->workflows, fn ($workflow) => $workflow->workflowId === $workflowId);

    }

    private function findStep(Workflow $workflow, string $stepId): ?Step
    {
        return array_find($workflow->steps, fn ($step) => $step->stepId === $stepId);

    }
}
