<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Execution\Contracts\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Execution\Contracts\StepProtocolExecutorInterface;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Step;

final class HttpStepExecutor implements StepProtocolExecutorInterface
{
    public function __construct(
        private OpenApiExecutorInterface $openApiExecutor,
        private ExpressionResolverInterface $expressionResolver,
        private OpenApiOperationResolver $operationResolver,
        private bool $strictValidationDefault = false,
        private ?IdempotencyKeyInjector $injector = null,
    ) {
    }

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return $step->action === null;
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
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

        $resolved = $this->operationResolver->resolve($step, $document);

        $response = $this->openApiExecutor->execute(
            $resolved,
            $payload,
            function ($request) use ($context, $step) {
                if ($this->injector !== null) {
                    return $this->injector->inject($request, $step, $context)->request;
                }

                return $request;
            },
        );

        $decodedBody = json_decode((string) $response->getBody(), true);
        $body = is_array($decodedBody) ? $decodedBody : [];

        if ($this->shouldValidateSchema($step)) {
            $this->expressionResolver->validateResponseSchema(
                $step,
                $response->getStatusCode(),
                $response->getHeaderLine('Content-Type'),
                $body,
                $document,
            );
        }

        $contextWithResponse = $context->withStepResponse($step->stepId, [
            'statusCode' => $response->getStatusCode(),
            'body' => $body,
        ]);

        $outputs = $this->expressionResolver->extractOutputs($step, $contextWithResponse, $document);

        return StepExecutionOutcome::resolved($response->getStatusCode(), $outputs, $body);
    }

    private function shouldValidateSchema(Step $step): bool
    {
        return $step->strictValidation ?? $this->strictValidationDefault;
    }
}
