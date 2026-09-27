<?php

declare(strict_types=1);

namespace Alama\Arazzo\RequestPipeline;

use Alama\Arazzo\Contracts\Interfaces\OutputExtractorInterface;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Evaluation\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\RequestPipeline\Data\ExecutionEvaluationInput;

/**
 * Request-pipeline implementation of the expression resolver seam.
 *
 * Engine-level services type against {@see ExpressionResolverInterface} for
 * the capabilities that aggregate execution concerns (output extraction,
 * schema validation) with pure expression evaluation. Composing that resolver
 * from the injected contracts — the evaluation engine, an
 * {@see OutputExtractorInterface} and a {@see ResponseValidatorInterface},
 * whichever implementations the composition root supplies — keeps the
 * evaluation internals out of every caller and lets the runner own no part of
 * it beyond the wiring.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ExecutionExpressionResolver implements ExpressionResolverInterface
{
    public function __construct(
        private EvaluationEngineInterface $engine,
        private OutputExtractorInterface $outputExtractor,
        private ResponseValidatorInterface $schemaValidator,
    ) {}

    public function evaluate(Expression $expression, WorkflowContextInterface $context, ?string $currentStepId = null): mixed
    {
        return $this->engine->evaluate($expression, new ExecutionEvaluationInput($context, $currentStepId));
    }

    public function extractOutputs(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): array
    {
        return $this->outputExtractor->extractOutputs($step, $context, $document);
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return $this->engine->evaluateSuccessCriteria($step, $context, $document);
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return $this->engine->evaluateCriteria($criteria, $step, $context, $document);
    }

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        $this->schemaValidator->validateResponseSchema($step, $statusCode, $contentType, $decodedBody, $document);
    }
}
