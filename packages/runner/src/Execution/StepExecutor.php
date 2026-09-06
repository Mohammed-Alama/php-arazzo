<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\Exceptions\SchemaValidationException;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Contracts\Support\Events\Dispatcher\NullEventDispatcher;
use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Document\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Document\Normalizer\ResolvedOperation;
use Alama\Arazzo\Expression\ExpressionEngineInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Execution\Interfaces\OpenApiExecutorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\RequestInterface as Psr7Request;
use Throwable;

class StepExecutor
{
    /** @phpstan-ignore property.onlyWritten */
    private EventDispatcherInterface $events;

    public function __construct(
        private OpenApiExecutorInterface $openApiExecutor,
        private ExpressionResolverInterface $expressionResolver,
        private OpenApiOperationResolver|DocumentInterface $operationResolver,
        private ExpressionEngineInterface $engine,
        private bool $strictValidationDefault = false,
        private ?IdempotencyKeyInjector $injector = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new NullEventDispatcher();
    }

    /**
     * Executes a step and returns an array with the updated WorkflowContext and a boolean success flag.
     *
     * @return array{0: WorkflowContext, 1: bool}
     */
    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document): array
    {
        ['payload' => $payload] =
            (new RequestCompiler(new ExpressionValueResolver($this->engine), $this->engine))->compile($step, $document, $context);

        $resolved = $this->resolveOperation($step, $document);

        try {
            $response = $this->openApiExecutor->execute(
                $resolved,
                $payload,
                function ($request) use (&$context, $step, $payload) {
                    if ($this->injector !== null) {
                        $request = $this->injector->inject($request, $step, $context)->request;
                    }

                    $bodyStream = $request->getBody();
                    $bodyStream->rewind();
                    $bodyData = json_decode($bodyStream->getContents(), true) ?? [];
                    $bodyStream->rewind();

                    $queryParams = [];
                    parse_str($request->getUri()->getQuery(), $queryParams);

                    $headers = [];
                    foreach ($request->getHeaders() as $name => $values) {
                        $headers[$name] = implode(', ', $values);
                    }

                    $captured = $request instanceof Psr7Request ? $request : null;
                    $context = $context->withStepRequest($step->stepId, RequestCompiler::requestRecord($captured, $payload));

                    return $request;
                },
                $step->timeout !== null ? $step->timeout / 1000 : null,
            );

            $decoded = RequestCompiler::decodeResponse($response);
            $statusCode = $decoded['statusCode'];
            $respHeaders = $decoded['headers'];
            $respBody = $decoded['body'];
            $respBodyString = $decoded['rawBody'];

            if ($this->shouldValidateSchema($step)) {
                $this->expressionResolver->validateResponseSchema(
                    $step,
                    $statusCode,
                    $response->getHeaderLine('Content-Type'),
                    $respBody,
                    $document,
                );
            }

            $context = $context->withStepResponse($step->stepId, [
                'statusCode' => $statusCode,
                'headers' => $respHeaders,
                'body' => $respBody,
                'rawBody' => $respBodyString,
                'contentType' => $response->getHeaderLine('Content-Type'),
            ]);
        } catch (SchemaValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $context = $context->withStepResponse($step->stepId, [
                'statusCode' => 500,
                'headers' => [],
                'body' => ['error' => $e->getMessage()],
                'failureCategory' => 'transport',
            ]);
        }

        $outputs = $this->expressionResolver->extractOutputs($step, $context, $document);
        foreach ($outputs as $key => $val) {
            $context = $context->withStepOutput($step->stepId, $key, $val);
        }

        $success = $this->expressionResolver->evaluateSuccessCriteria($step, $context, $document);

        return [$context, $success];
    }

    private function shouldValidateSchema(Step $step): bool
    {
        return $step->strictValidation ?? $this->strictValidationDefault;
    }

    private function resolveOperation(Step $step, ArazzoDocument $document): ResolvedOperation
    {
        if ($this->operationResolver instanceof DocumentInterface) {
            return $this->operationResolver->resolveOperation($step, $document);
        }

        return $this->operationResolver->resolve($step, $document);
    }
}
