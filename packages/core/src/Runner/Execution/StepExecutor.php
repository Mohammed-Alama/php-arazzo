<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Evaluation\PayloadReplacer;
use Alama\Arazzo\Runner\Evaluation\StringInterpolator;
use Alama\Arazzo\Runner\Exceptions\SchemaValidationException;
use Alama\Arazzo\Runner\Execution\Contracts\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\PayloadReplacement;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Support\Events\Dispatcher\NullEventDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

class StepExecutor
{
    /** @phpstan-ignore property.onlyWritten */
    private EventDispatcherInterface $events;

    private StringInterpolator $interpolator;

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
        $this->interpolator = $interpolator ?? new StringInterpolator($this->expressionResolver);
    }

    /**
     * Executes a step and returns an array with the updated WorkflowContext and a boolean success flag.
     *
     * @return array{0: WorkflowContext, 1: bool}
     */
    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document): array
    {
        $payload = new OpenApiPayload();

        $parameters = (new ReusableParameterResolver())->resolve($step->parameters, $document);
        foreach ($parameters as $param) {
            $val = $this->resolveValue($param->value, $context, $step->stepId);

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
            $bodyData = PayloadReplacer::apply(
                $step,
                is_array($step->requestBody->payload) ? $step->requestBody->payload : [],
                fn (PayloadReplacement $replacement) => $this->resolveValue($replacement->value, $context, $step->stepId),
            );
        }
        $payload->body = empty($bodyData) ? null : $bodyData;

        $resolved = $this->operationResolver->resolve($step, $document);

        try {
            $response = $this->openApiExecutor->execute(
                $resolved,
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
                },
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

    private function shouldValidateSchema(Step $step): bool
    {
        return $step->strictValidation ?? $this->strictValidationDefault;
    }

    private function resolveValue(mixed $value, WorkflowContext $context, string $stepId): mixed
    {
        if ($value instanceof Expression) {
            return $this->expressionResolver->evaluate($value, $context, $stepId);
        }

        if (is_string($value) && str_contains($value, '{$')) {
            return $this->interpolator->interpolate($value, $context, $stepId);
        }

        // Arazzo parameter/payload values may use the bare runtime-expression
        // spellings (`$inputs.x`, `${inputs.x}`); normalize them into the
        // interpolator's `{$...}` template form before evaluation.
        if (is_string($value) && preg_match('/^\$[{$]?[A-Za-z]/', $value) === 1 && !str_contains($value, ' ')) {
            $template = $value[1] === '{' ? $value : '{' . $value . '}';

            return $this->interpolator->interpolate($template, $context, $stepId);
        }

        return $value;
    }
}
