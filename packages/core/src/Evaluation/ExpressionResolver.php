<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Evaluation\Data\EvaluationContext;
use Alama\Arazzo\Evaluation\Interfaces\CriteriaEvaluatorInterface;
use Alama\Arazzo\Execution\Interfaces\OutputExtractorInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEvaluatorInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Validator\Interfaces\ResponseValidatorInterface;

class ExpressionResolver implements ExpressionResolverInterface
{
    public function __construct(
        private ExpressionEvaluatorInterface $evaluator,
        private OutputExtractorInterface $outputExtractor,
        private CriteriaEvaluatorInterface $criteriaEvaluator,
        private ResponseValidatorInterface $schemaValidator,
    ) {}

    public function evaluate(Expression $expression, WorkflowContextInterface $context, ?string $currentStepId = null): mixed
    {
        return $this->evaluator->evaluate($expression, new EvaluationContext($context, $currentStepId));
    }

    public function extractOutputs(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): array
    {
        return $this->outputExtractor->extractOutputs($step, $context, $document);
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return $this->criteriaEvaluator->evaluateSuccessCriteria($step, $context, $document);
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return $this->criteriaEvaluator->evaluateCriteria($criteria, $step, $context, $document);
    }

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        $this->schemaValidator->validateResponseSchema($step, $statusCode, $contentType, $decodedBody, $document);
    }
}
