<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Events\Dispatcher\NullEventDispatcher;
use Alama\Arazzo\Runner\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Contracts\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Dto\OpenApiPayload;
use Alama\Arazzo\Runner\Exceptions\SchemaValidationException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

class StepExecutor
{
    /** @phpstan-ignore property.onlyWritten */
    private EventDispatcherInterface $events;

    public function __construct(
        private OpenApiExecutorInterface $openApiExecutor,
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
        $payload = new OpenApiPayload();

        foreach ($step->parameters as $param) {
            $val = $param->value instanceof Expression
                ? $this->expressionResolver->evaluate($param->value, $context, $step->stepId)
                : $param->value;

            $in = $param->in?->value ?? 'auto';
            if ($in === 'query') {
                $payload->query[$param->name] = $val;
            } elseif ($in === 'header') {
                $payload->header[$param->name] = $val;
            } elseif ($in === 'path') {
                $payload->path[$param->name] = $val;
            } else {
                $payload->auto[$param->name] = $val;
            }
        }

        $bodyData = [];
        if ($step->requestBody && $step->requestBody->payload !== null) {
            $bodyData = $step->requestBody->payload;
            if ($step->requestBody->replacements) {
                foreach ($step->requestBody->replacements as $replacement) {
                    $targetPtr = $replacement->target;
                    $val = $replacement->value instanceof Expression
                        ? $this->expressionResolver->evaluate($replacement->value, $context, $step->stepId)
                        : $replacement->value;

                    $segments = explode('/', ltrim($targetPtr, '/'));
                    $current = &$bodyData;
                    foreach ($segments as $i => $segment) {
                        $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
                        if ($i === count($segments) - 1) {
                            $current[$segment] = $val;
                        } else {
                            if (!isset($current[$segment])) {
                                $current[$segment] = [];
                            }
                            $current = &$current[$segment];
                        }
                    }
                }
            }
        }
        $payload->body = empty($bodyData) ? null : $bodyData;

        $sourceDesc = $document->sourceDescriptions[0] ?? null;
        if ($sourceDesc === null) {
            throw new \RuntimeException("No SourceDescription found in document");
        }

        $operation = $step->operationId ?? $step->operationPath ?? '/';

        try {
            $response = $this->openApiExecutor->execute(
                $sourceDesc,
                $operation,
                $payload,
                function ($request) use (&$context, $step) {
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

                    $context = $context->withStepRequest($step->stepId, [
                        'method' => $request->getMethod(),
                        'url' => (string) $request->getUri(),
                        'query' => $queryParams,
                        'headers' => $headers,
                        'body' => $bodyData,
                    ]);

                    return $request;
                }
            );

            $statusCode = $response->getStatusCode();
            $respHeaders = [];
            foreach ($response->getHeaders() as $name => $values) {
                $respHeaders[$name] = implode(', ', $values);
            }

            $respBodyString = (string) $response->getBody();
            $respBody = json_decode($respBodyString, true) ?? [];

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

        $outputs = $this->expressionResolver->extractOutputs($step, $context, $document);
        foreach ($outputs as $key => $val) {
            $context = $context->withStepOutput($step->stepId, $key, $val);
        }

        $success = $this->expressionResolver->evaluateSuccessCriteria($step, $context, $document);

        return [$context, $success];
    }
}
