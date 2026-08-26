<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution;

use Alama\Arazzo\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Contracts\OpenApiExecutorInterface;
use Alama\Arazzo\Exceptions\SchemaValidationException;
use Alama\Arazzo\Expression\StringInterpolator;
use Alama\Arazzo\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\State\WorkflowContext;
use Alama\Arazzo\Support\Events\Dispatcher\NullEventDispatcher;
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
        private OpenApiOperationResolver $operationResolver,
        private bool $strictValidationDefault = false,
        private ?IdempotencyKeyInjector $injector = null,
        ?EventDispatcherInterface $events = null,
        ?StringInterpolator $interpolator = null,
    ) {
        $this->events = $events ?? new NullEventDispatcher();
        unset($interpolator); // kept for BC; interpolation now flows through ExpressionValueResolver
    }

    /**
     * Executes a step and returns an array with the updated WorkflowContext and a boolean success flag.
     *
     * @return array{0: WorkflowContext, 1: bool}
     */
    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document): array
    {
        ['payload' => $payload] =
            (new RequestCompiler(new ExpressionValueResolver($this->expressionResolver)))->compile($step, $document, $context);

        $resolved = $this->operationResolver->resolve($step, $document);

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
}
