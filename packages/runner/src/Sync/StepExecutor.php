<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Sync;

use Alama\Arazzo\Contracts\Exceptions\SchemaValidationException;
use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Interfaces\OutputExtractorInterface;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Contracts\Support\Events\Dispatcher\NullEventDispatcher;
use Alama\Arazzo\Engine\Exceptions\ExecutionException;
use Alama\Arazzo\Engine\Execution\IdempotencyKeyInjector;
use Alama\Arazzo\Engine\Execution\RequestCompiler;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\OperationExecutorRegistry;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationHandle;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
class StepExecutor
{
    /** @phpstan-ignore property.onlyWritten */
    private EventDispatcherInterface $events;

    /** @phpstan-ignore-next-line */
    public function __construct(
        private OpenApiExecutorInterface $openApiExecutor,
        private OpenApiOperationResolver $operationResolver,
        private EvaluationEngineInterface $evaluationEngine,
        private OutputExtractorInterface $outputExtractor,
        private ResponseValidatorInterface $schemaValidator,
        private bool $strictValidationDefault = false,
        private ?IdempotencyKeyInjector $injector = null,
        ?EventDispatcherInterface $events = null,
        private ?OperationExecutorRegistry $executors = null,
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
        $plugin = $this->executors?->resolve($step, $document);

        if ($plugin !== null) {
            return $this->executeWithPlugin($plugin, $step, $context, $document);
        }

        return $this->executeHttp($step, $context, $document);
    }

    /**
     * @return array{0: WorkflowContext, 1: bool}
     */
    private function executeWithPlugin(
        OperationExecutorPluginInterface $plugin,
        Step $step,
        WorkflowContext $context,
        ArazzoDocument $document,
    ): array {
        $outcome = $plugin->execute($step, $context, $document, $context->getExecutionId());

        if ($outcome->suspended) {
            throw new ExecutionException("Step '{$step->stepId}' suspended, which the synchronous runner cannot resume.");
        }

        $context = $context->withStepRequest($step->stepId, $outcome->request ?? []);
        $context = $context->withStepResponse($step->stepId, [
            'statusCode' => $outcome->statusCode,
            'headers' => $outcome->responseHeaders,
            'body' => $outcome->responseBody,
        ]);

        // A workflow-invocation step records the child workflow's outputs before
        // the step's declared `outputs` map is evaluated, so `$outputs.<name>`
        // resolves to the child output exactly as the async worker does it.
        foreach ($outcome->outputs as $name => $value) {
            $context = $context->withStepOutput($step->stepId, $name, $value);
        }
        foreach ($this->outputExtractor->extractOutputs($step, $context, $document) as $name => $value) {
            $context = $context->withStepOutput($step->stepId, $name, $value);
        }

        return [$context, $this->evaluationEngine->evaluateSuccessCriteria($step, $context, $document)];
    }

    /**
     * @return array{0: WorkflowContext, 1: bool}
     */
    private function executeHttp(Step $step, WorkflowContext $context, ArazzoDocument $document): array
    {
        $compiler = new RequestCompiler($this->evaluationEngine);
        ['payload' => $payload] = $compiler->compile($step, $document, $context);

        $resolved = $this->resolveOperation($step, $document);

        try {
            $response = $this->openApiExecutor->execute(
                $resolved,
                $payload,
                function (RequestInterface $request) use (&$context, $compiler, $step, $payload) {
                    if ($this->injector !== null) {
                        $request = $this->injector->inject($request, $step, $context)->request;
                    }

                    // The record below reads the payload, not the stream, but the
                    // caller still sends this request: rewind so an injector that
                    // read the body cannot leave the cursor past the start.
                    $request->getBody()->rewind();

                    $captured = $request;
                    $context = $context->withStepRequest($step->stepId, $compiler->requestRecord($captured, $payload));

                    return $request;
                },
                $step->flow->timeout !== null ? $step->flow->timeout / 1000 : null,
            );

            $decoded = $compiler->decodeResponse($response);
            $statusCode = $decoded['statusCode'];
            $respHeaders = $decoded['headers'];
            $respBody = $decoded['body'];
            $respBodyString = $decoded['rawBody'];

            if ($this->shouldValidateSchema($step)) {
                $this->schemaValidator->validateResponseSchema(
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

        $outputs = $this->outputExtractor->extractOutputs($step, $context, $document);
        foreach ($outputs as $key => $val) {
            $context = $context->withStepOutput($step->stepId, $key, $val);
        }

        $success = $this->evaluationEngine->evaluateSuccessCriteria($step, $context, $document);

        return [$context, $success];
    }

    private function shouldValidateSchema(Step $step): bool
    {
        return $step->flow->strictValidation ?? $this->strictValidationDefault;
    }

    private function resolveOperation(Step $step, ArazzoDocument $document): OpenApiOperationHandle
    {
        return $this->operationResolver->resolve($step, $document);
    }
}
