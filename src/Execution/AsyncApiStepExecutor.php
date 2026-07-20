<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\StepProtocolExecutorInterface;
use LogicException;

final class AsyncApiStepExecutor implements StepProtocolExecutorInterface
{
    public function __construct(
        private PendingCorrelationRegistryInterface $pendingCorrelations,
        private ExpressionEvaluator $evaluator,
        private HttpClientInterface $httpClient,
        private ExpressionResolverInterface $expressionResolver,
    ) {
    }

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return $step->action !== null;
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        if ($step->action === 'send') {
            $request = $this->expressionResolver->compileRequest($step, $context, $document);
            $response = $this->httpClient->sendRequest($request);

            return StepExecutionOutcome::resolved($response->getStatusCode(), [], []);
        }

        if ($step->correlationId === null) {
            throw new LogicException("Step '{$step->stepId}' has action 'receive' but no correlationId expression.");
        }
        if ($step->channelPath === null) {
            throw new LogicException("Step '{$step->stepId}' has action 'receive' but no channelPath.");
        }

        $correlationId = (string) $this->evaluator->evaluate($step->correlationId, $context, $step->stepId);

        $this->pendingCorrelations->create($correlationId, $executionId, $step->stepId, $step->channelPath);

        return StepExecutionOutcome::suspended();
    }
}
