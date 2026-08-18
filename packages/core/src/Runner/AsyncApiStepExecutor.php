<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Enum\SpecVersion;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Runner\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Contracts\HttpClientInterface;
use Alama\Arazzo\Runner\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\Contracts\StepProtocolExecutorInterface;
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
        return in_array($step->action, ['send', 'receive'], true);
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        if ($document->specVersion === SpecVersion::V1_0) {
            throw new LogicException(
                "AsyncAPI step '{$step->stepId}' encountered under a 1.0.0 document; upgrade to arazzo: 1.1.0.",
            );
        }

        if ($step->action === 'send') {
            $request = $this->expressionResolver->compileRequest($step, $context, $document);
            $response = $this->httpClient->sendRequest($request);

            return StepExecutionOutcome::resolved($response->getStatusCode(), [], []);
        }

        if ($step->action !== 'receive') {
            throw new LogicException("Unsupported action '{$step->action}' for step '{$step->stepId}'.");
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
