<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Execution\Contracts\CriteriaEvaluatorInterface;
use Alama\Arazzo\Execution\Contracts\ExpressionEvaluatorInterface;
use Alama\Arazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Execution\Contracts\OutputExtractorInterface;
use Alama\Arazzo\Execution\Contracts\RequestCompilerInterface;
use Alama\Arazzo\Execution\Contracts\SchemaValidatorInterface;
use Psr\Http\Message\RequestInterface;

/**
 * @deprecated Use specific implementations instead
 */
class ArazzoExpressionResolver implements ExpressionResolverInterface
{
    public function __construct(
        private ExpressionEvaluatorInterface $evaluator,
        private RequestCompilerInterface $requestCompiler,
        private OutputExtractorInterface $outputExtractor,
        private CriteriaEvaluatorInterface $criteriaEvaluator,
        private SchemaValidatorInterface $schemaValidator,
    ) {
    }

    public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed
    {
        return $this->evaluator->evaluate($expression, $context, $currentStepId);
    }

    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
    {
        return $this->requestCompiler->compileRequest($step, $context, $document);
    }

    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        return $this->outputExtractor->extractOutputs($step, $context, $document);
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return $this->criteriaEvaluator->evaluateSuccessCriteria($step, $context, $document);
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return $this->criteriaEvaluator->evaluateCriteria($criteria, $step, $context, $document);
    }

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        $this->schemaValidator->validateResponseSchema($step, $statusCode, $contentType, $decodedBody, $document);
    }
}
