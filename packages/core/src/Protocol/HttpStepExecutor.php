<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol;

use Alama\Arazzo\Execution\ExpressionValueResolver;
use Alama\Arazzo\Execution\IdempotencyKeyInjector;
use Alama\Arazzo\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Execution\RequestCompiler;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Protocol\Interfaces\StepProtocolExecutorInterface;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\StepExecutionOutcome;
use Alama\Arazzo\Spec\WorkflowContext;
use Psr\Http\Message\RequestInterface as Psr7Request;

final class HttpStepExecutor implements StepProtocolExecutorInterface
{
    public function __construct(
        private OpenApiExecutorInterface $openApiExecutor,
        private ExpressionResolverInterface $expressionResolver,
        private OpenApiOperationResolver $operationResolver,
        private bool $strictValidationDefault = false,
        private ?IdempotencyKeyInjector $injector = null,
    ) {}

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return $step->action === null;
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        ['payload' => $payload, 'resolvedInputs' => $resolvedInputs] =
            (new RequestCompiler(new ExpressionValueResolver($this->expressionResolver)))->compile($step, $document, $context);

        $resolved = $this->operationResolver->resolve($step, $document);

        $capturedRequest = null;
        try {
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
                $step->timeout !== null ? $step->timeout / 1000 : null,
            );
        } catch (\Throwable $e) {
            // Transport-level failures become a synthetic 500 response so
            // failureActions (e.g. retry) apply identically to the sync
            // adapter's StepExecutor policy.
            return StepExecutionOutcome::resolved(500, [], ['error' => $e->getMessage()], failureCategory: 'transport');
        }

        $decoded = RequestCompiler::decodeResponse($response);

        if ($this->shouldValidateSchema($step)) {
            $this->expressionResolver->validateResponseSchema(
                $step,
                $decoded['statusCode'],
                $decoded['contentType'],
                $decoded['body'],
                $document,
            );
        }

        $requestRecord = RequestCompiler::requestRecord($capturedRequest, $payload);

        $contextWithResponse = $context
            ->withStepRequest($step->stepId, $requestRecord)
            ->withStepResponse($step->stepId, [
                'statusCode' => $decoded['statusCode'],
                'headers' => $decoded['headers'],
                'body' => $decoded['body'],
            ]);

        $outputs = $this->expressionResolver->extractOutputs($step, $contextWithResponse, $document);

        return StepExecutionOutcome::resolved(
            $decoded['statusCode'],
            $outputs,
            $decoded['body'],
            $resolvedInputs,
            $requestRecord,
            $decoded['headers'],
            rawBody: $decoded['rawBody'],
            contentType: $decoded['contentType'],
        );
    }

    private function shouldValidateSchema(Step $step): bool
    {
        return $step->strictValidation ?? $this->strictValidationDefault;
    }
}
