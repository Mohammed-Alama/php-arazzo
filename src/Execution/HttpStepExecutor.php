<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\StepProtocolExecutorInterface;

final class HttpStepExecutor implements StepProtocolExecutorInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ExpressionResolverInterface $expressionResolver,
        private bool $strictValidationDefault = false,
    ) {
    }

    private function shouldValidateSchema(Step $step): bool
    {
        return $step->strictValidation ?? $this->strictValidationDefault;
    }

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return $step->action === null;
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        $request = $this->expressionResolver->compileRequest($step, $context, $document);
        $response = $this->httpClient->sendRequest($request);

        $decodedBody = json_decode((string) $response->getBody(), true);
        $body = is_array($decodedBody) ? $decodedBody : [];

        if ($this->shouldValidateSchema($step)) {
            $this->expressionResolver->validateResponseSchema(
                $step,
                $response->getStatusCode(),
                $response->getHeaderLine('Content-Type'),
                $body,
                $document
            );
        }

        $contextWithResponse = $context->withStepResponse($step->stepId, [
            'statusCode' => $response->getStatusCode(),
            'body' => $body,
        ]);

        $outputs = $this->expressionResolver->extractOutputs($step, $contextWithResponse, $document);

        return StepExecutionOutcome::resolved($response->getStatusCode(), $outputs, $body);
    }
}
