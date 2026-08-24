<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Evaluation\PayloadReplacer;
use Alama\Arazzo\Runner\Execution\Contracts\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Execution\Contracts\StepProtocolExecutorInterface;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\PayloadReplacement;
use Alama\Arazzo\Spec\Step;
use Psr\Http\Message\RequestInterface as Psr7Request;

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

        $resolvedInputs = [];
        $parameters = (new ReusableParameterResolver())->resolve($step->parameters, $document);
        foreach ($parameters as $param) {
            $val = $param->value instanceof Expression
                ? $this->expressionResolver->evaluate($param->value, $context, $step->stepId)
                : $param->value;

            $resolvedInputs[$param->name] = $val;

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
                fn (mixed $replacement) => $this->resolveReplacementValue($replacement, $context),
            );
        }
        $payload->body = empty($bodyData) ? null : $bodyData;

        $resolved = $this->operationResolver->resolve($step, $document);

        $capturedRequest = null;
        $response = $this->openApiExecutor->execute(
            $resolved,
            $payload,
            function (Psr7Request $request) use ($context, $step, &$capturedRequest) {
                $capturedRequest = $request;

                if ($this->injector !== null) {
                    return $this->injector->inject($request, $step, $context)->request;
                }

                return $request;
            },
            $step->timeout,
        );

        $decodedBody = json_decode((string) $response->getBody(), true);
        $body = is_array($decodedBody) ? $decodedBody : [];

        $responseHeaders = [];
        foreach ($response->getHeaders() as $name => $values) {
            if (!is_string($name)) {
                continue;
            }

            $responseHeaders[$name] = implode(', ', array_map(strval(...), $values));
        }

        if ($this->shouldValidateSchema($step)) {
            $this->expressionResolver->validateResponseSchema(
                $step,
                $response->getStatusCode(),
                $response->getHeaderLine('Content-Type'),
                $body,
                $document,
            );
        }

        $queryParams = [];
        parse_str($capturedRequest?->getUri()->getQuery() ?? '', $queryParams);

        $requestHeaders = [];
        foreach ($capturedRequest?->getHeaders() ?? [] as $name => $values) {
            $requestHeaders[$name] = implode(', ', $values);
        }

        $requestRecord = [
            'method' => $capturedRequest?->getMethod(),
            'url' => $capturedRequest !== null ? (string) $capturedRequest->getUri() : '',
            'query' => $queryParams,
            'path' => $payload->path,
            'headers' => $requestHeaders,
            'body' => is_array($payload->body) ? $payload->body : [],
        ];

        $contextWithResponse = $context
            ->withStepRequest($step->stepId, $requestRecord)
            ->withStepResponse($step->stepId, [
                'statusCode' => $response->getStatusCode(),
                'headers' => $responseHeaders,
                'body' => $body,
            ]);

        $outputs = $this->expressionResolver->extractOutputs($step, $contextWithResponse, $document);

        return StepExecutionOutcome::resolved($response->getStatusCode(), $outputs, $body, $resolvedInputs, $requestRecord, $responseHeaders);
    }

    private function resolveReplacementValue(PayloadReplacement $replacement, WorkflowContext $context): mixed
    {
        $value = $replacement->value;

        return $value instanceof Expression
            ? $this->expressionResolver->evaluate($value, $context, null)
            : $value;
    }

    private function shouldValidateSchema(Step $step): bool
    {
        return $step->strictValidation ?? $this->strictValidationDefault;
    }
}
