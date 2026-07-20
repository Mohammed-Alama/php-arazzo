<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Psr\Http\Client\ClientInterface;
use Throwable;

class StepExecutor
{
    public function __construct(
        private ClientInterface $httpClient,
        private ExpressionResolverInterface $expressionResolver,
    ) {
    }

    /**
     * Executes a step and returns an array with the updated WorkflowContext and a boolean success flag.
     *
     * @return array{0: WorkflowContext, 1: bool}
     */
    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document): array
    {
        // 1. Compile Request
        $request = $this->expressionResolver->compileRequest($step, $context, $document);

        // Parse body back to array for context storage
        $bodyStream = $request->getBody();
        $bodyStream->rewind();
        $bodyData = json_decode($bodyStream->getContents(), true) ?? [];
        $bodyStream->rewind();

        // Convert query string back to array
        $queryParams = [];
        parse_str($request->getUri()->getQuery(), $queryParams);

        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        // Add request to context immutably
        $context = $context->withStepRequest($step->stepId, [
            'method' => $request->getMethod(),
            'url' => (string) $request->getUri(),
            'query' => $queryParams,
            'headers' => $headers,
            'body' => $bodyData,
        ]);

        // 2. Send HTTP Request
        try {
            $response = $this->httpClient->sendRequest($request);

            $statusCode = $response->getStatusCode();
            $respHeaders = [];
            foreach ($response->getHeaders() as $name => $values) {
                $respHeaders[$name] = implode(', ', $values);
            }

            $respBodyString = (string) $response->getBody();
            $respBody = json_decode($respBodyString, true) ?? [];

            $context = $context->withStepResponse($step->stepId, [
                'statusCode' => $statusCode,
                'headers' => $respHeaders,
                'body' => $respBody,
            ]);
        } catch (Throwable $e) {
            $context = $context->withStepResponse($step->stepId, [
                'statusCode' => 500,
                'headers' => [],
                'body' => ['error' => $e->getMessage()],
            ]);
        }

        // 3. Extract Outputs
        $outputs = $this->expressionResolver->extractOutputs($step, $context, $document);
        foreach ($outputs as $key => $val) {
            $context = $context->withStepOutput($step->stepId, $key, $val);
        }

        // 4. Evaluate Success
        $success = $this->expressionResolver->evaluateSuccessCriteria($step, $context, $document);

        return [$context, $success];
    }
}
