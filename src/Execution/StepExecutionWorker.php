<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;

class StepExecutionWorker
{
    public function __construct(
        private LockManagerInterface $lockManager,
        private StateStoreInterface $stateStore,
        private Engine $engine,
        private HttpClientInterface $httpClient,
        private ExpressionResolverInterface $expressionResolver,
        private DefinitionRegistryInterface $definitionRegistry,
    ) {
    }

    public function handle(ExecuteStepJob $job): void
    {
        $lockKey = "workflow_lock_{$job->context->getDefinitionId()}";

        $this->lockManager->acquire($lockKey, 30, function () use ($job) {
            $context = $job->context;
            $step = $job->step;

            // Idempotency check
            if (array_key_exists($step->stepId, $context->getSteps())) {
                return;
            }

            $request = $this->expressionResolver->compileRequest($step, $context);

            // Note: In real scenarios, we would handle RateLimitException here
            $response = $this->httpClient->sendRequest($request);

            // Assuming successful for MVP logic. Next iteration would evaluate criteria.
            $outputs = $this->expressionResolver->extractOutputs($step, $context);

            // Mutate context
            $newContext = $context->withStepResult($step->stepId, [
                'statusCode' => $response->getStatusCode(),
                'outputs' => $outputs,
            ]);

            // Save state
            $this->stateStore->save($newContext->getDefinitionId(), [
                'definitionId' => $newContext->getDefinitionId(),
                'steps' => $newContext->getSteps(),
                'inputs' => $newContext->getInputs(),
                'components' => $newContext->getComponents(),
            ]);

            // Fire event (commented out for this step to avoid depending on Laravel events directly in core class if not injected, or we can use Laravel event helper later)
            // event(new \Alama\LaravelArazzo\Execution\Events\StepExecuted(...));

            // Choreograph: look up the full workflow and dispatch any newly-unlocked steps.
            $workflow = $this->definitionRegistry->get($newContext->getDefinitionId());
            if ($workflow !== null) {
                $this->engine->evaluate($workflow, $newContext);
            }
        });
    }
}
