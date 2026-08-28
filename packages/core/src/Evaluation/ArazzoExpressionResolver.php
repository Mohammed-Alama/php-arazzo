<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Interfaces\CriteriaEvaluatorInterface;
use Alama\Arazzo\Interfaces\ExpressionEvaluatorInterface;
use Alama\Arazzo\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Interfaces\OutputExtractorInterface;
use Alama\Arazzo\Interfaces\SchemaValidatorInterface;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\WorkflowContext;

class ArazzoExpressionResolver implements ExpressionResolverInterface
{
    public function __construct(
        private ExpressionEvaluatorInterface $evaluator,
        private OutputExtractorInterface $outputExtractor,
        private CriteriaEvaluatorInterface $criteriaEvaluator,
        private SchemaValidatorInterface $schemaValidator,
    ) {}

    public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed
    {
        return $this->evaluator->evaluate($expression, new EvaluationContext($context, $currentStepId));
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
