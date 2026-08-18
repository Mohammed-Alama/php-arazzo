<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Events\Dispatcher\NullEventDispatcher;
use Alama\Arazzo\Runner\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Exceptions\SchemaValidationException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Throwable;

class StepExecutor
{
    /** @phpstan-ignore property.onlyWritten */
    private EventDispatcherInterface $events;

    public function __construct(
        private ClientInterface $httpClient,
        private ExpressionResolverInterface $expressionResolver,
        private bool $strictValidationDefault = false,
        private ?IdempotencyKeyInjector $injector = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new NullEventDispatcher();
    }

    private function shouldValidateSchema(Step $step): bool
    {
        return $step->strictValidation ?? $this->strictValidationDefault;
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

        if ($this->injector !== null) {
            $request = $this->injector->inject($request, $step, $context)->request;
        }

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

            // Validating response schema if required
            if ($this->shouldValidateSchema($step)) {
                $this->expressionResolver->validateResponseSchema(
                    $step,
                    $response->getStatusCode(),
                    $response->getHeaderLine('Content-Type'),
                    $respBody,
                    $document,
                );
            }

            $context = $context->withStepResponse($step->stepId, [
                'statusCode' => $statusCode,
                'headers' => $respHeaders,
                'body' => $respBody,
            ]);
        } catch (SchemaValidationException $e) {
            throw $e;
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
