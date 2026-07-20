<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use LogicException;
use Psr\Log\LoggerInterface;

class StepExecutionWorker
{
    public function __construct(
        private LockManagerInterface $lockManager,
        private StateStoreInterface $stateStore,
        private Engine $engine,
        private HttpClientInterface $httpClient,
        private ExpressionResolverInterface $expressionResolver,
        private DefinitionRegistryInterface $definitionRegistry,
        private EventLedgerInterface $eventLedger,
        private ExecutionRegistryInterface $executionRegistry,
        private ?LoggerInterface $logger = null,
        private int $stateTtlSeconds = 86400,
    ) {
    }

    public function handle(ExecuteStepJob $job): void
    {
        $executionId = $job->context->getExecutionId();
        if ($executionId === null) {
            throw new LogicException(
                "ExecuteStepJob for step '{$job->step->stepId}' has no executionId -- the workflow run was not initialized before dispatch."
            );
        }

        $lockKey = "workflow_lock_{$executionId}";

        $this->lockManager->acquire($lockKey, 30, function () use ($job, $executionId) {
            $context = $job->context;
            $step = $job->step;

            $latestState = $this->stateStore->load($executionId);
            if ($latestState !== null) {
                if (array_key_exists($step->stepId, $latestState['steps'] ?? [])) {
                    return;
                }
                $context = new WorkflowContext(
                    $latestState['definitionId'] ?? $context->getDefinitionId(),
                    $latestState['inputs'] ?? $context->getInputs(),
                    $latestState['steps'] ?? $context->getSteps(),
                    $latestState['components'] ?? $context->getComponents(),
                    $latestState['workflowId'] ?? $context->getWorkflowId(),
                    $executionId
                );
            } else {
                if (array_key_exists($step->stepId, $context->getSteps())) {
                    return;
                }
            }

            $document = $this->definitionRegistry->get($context->getDefinitionId());
            if ($document === null) {
                $this->eventLedger->append($executionId, 'execution.definition_missing', [
                    'definitionId' => $context->getDefinitionId(),
                ]);

                return;
            }

            $request = $this->expressionResolver->compileRequest($step, $context, $document);

            // Note: In real scenarios, we would handle RateLimitException here
            $response = $this->httpClient->sendRequest($request);

            $outputs = $this->expressionResolver->extractOutputs($step, $context, $document);

            $newContext = $context->withStepResult($step->stepId, [
                'statusCode' => $response->getStatusCode(),
                'outputs' => $outputs,
            ]);

            $this->stateStore->save($executionId, [
                'definitionId' => $newContext->getDefinitionId(),
                'workflowId' => $newContext->getWorkflowId(),
                'steps' => $newContext->getSteps(),
                'inputs' => $newContext->getInputs(),
                'components' => $newContext->getComponents(),
            ], $this->stateTtlSeconds);

            $workflowId = $newContext->getWorkflowId();
            if ($workflowId !== null) {
                $this->executionRegistry->start($executionId, $newContext->getDefinitionId(), $workflowId);
            }

            $this->eventLedger->append($executionId, 'step.executed', [
                'stepId' => $step->stepId,
                'statusCode' => $response->getStatusCode(),
                'outputs' => $outputs,
            ]);

            $workflow = null;
            foreach ($document->workflows as $candidate) {
                if ($candidate->workflowId === $workflowId) {
                    $workflow = $candidate;

                    break;
                }
            }

            if ($workflow !== null) {
                $this->engine->evaluate($workflow, $newContext);
            }
        });
    }
}
