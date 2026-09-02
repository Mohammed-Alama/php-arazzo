<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Evaluation\Condition\ConditionEvaluator;
use Alama\Arazzo\Evaluation\Condition\ConditionSyntaxException;
use Alama\Arazzo\Evaluation\Interfaces\CriteriaEvaluatorInterface;
use Alama\Arazzo\Execution\Data\WorkflowContext;
use Alama\Arazzo\Expression\Interfaces\ExpressionEvaluatorInterface;
use Alama\Arazzo\Expression\JsonPathEvaluator;
use Alama\Arazzo\Expression\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Expression\Xpath\XpathEvaluator;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\CriterionType;
use Alama\Arazzo\Spec\EvaluationContext;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\SuccessCriterion;

class ArazzoCriteriaEvaluator implements CriteriaEvaluatorInterface
{
    private ConditionEvaluator $conditionEvaluator;

    private ?XpathEvaluator $xpathEvaluator = null;

    public function __construct(
        private ExpressionEvaluatorInterface $evaluator,
        ?ConditionEvaluator $conditionEvaluator = null,
        ?XpathEvaluator $xpathEvaluator = null,
    ) {
        $this->conditionEvaluator = $conditionEvaluator ?? new ConditionEvaluator($evaluator);
        $this->xpathEvaluator = $xpathEvaluator;
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        // Default success behavior: an operation step without explicit criteria
        // passes on any 2xx response (community/spec-tooling convention).
        if ($this->hasOperationTarget($step) && $step->successCriteria === []) {
            $steps = $context->getSteps();
            $stepData = $steps[$step->stepId] ?? null;
            $response = is_array($stepData) ? ($stepData['response'] ?? null) : null;

            return self::isSuccessStatusCode(is_array($response) ? ($response['statusCode'] ?? null) : null);
        }

        return $this->evaluateCriteria($step->successCriteria, $step, $context, $document);
    }

    /**
     * @param  list<SuccessCriterion>  $criteria
     */
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        if (empty($criteria)) {
            return true;
        }

        $steps = $context->getSteps();
        $stepData = $steps[$step->stepId] ?? null;
        $response = is_array($stepData) ? ($stepData['response'] ?? null) : null;
        $responseBody = is_array($response) ? ($response['body'] ?? []) : [];

        foreach ($criteria as $criterion) {
            $type = $criterion->type ?? CriterionType::Simple;

            // Evaluation errors inside each branch fail the criterion deterministically.
            $passed = match ($type) {
                CriterionType::Simple => $this->evaluateSimple($criterion, $context, $step->stepId, $document),
                CriterionType::Regex => $this->evaluateRegex($criterion, $context, $step->stepId, $document),
                CriterionType::JsonPath => $this->evaluateJsonPath($criterion, $responseBody, $context, $step->stepId, $document),
                CriterionType::XPath => $this->evaluateXPath($criterion, $context, $step->stepId, $document),
            };

            if (!$passed) {
                return false;
            }
        }

        return true;
    }

    private function evaluateSimple(SuccessCriterion $criterion, WorkflowContext $context, string $stepId, ?ArazzoDocument $document): bool
    {
        try {
            return $this->conditionEvaluator->evaluate($criterion->condition, $context, $stepId, $document);
        } catch (ConditionSyntaxException) {
            // Evaluation errors fail the criterion deterministically.
            return false;
        }
    }

    private function evaluateRegex(SuccessCriterion $criterion, WorkflowContext $context, string $stepId, ?ArazzoDocument $document): bool
    {
        if ($criterion->context === null) {
            // A regex criterion without a context cannot be evaluated; fail deterministically.
            return false;
        }

        try {
            $target = $this->evaluator->evaluate(new Expression($criterion->context), new EvaluationContext($context, $stepId, $document));
        } catch (\Throwable) {
            return false;
        }

        if ($target === null) {
            return false;
        }

        return preg_match('/'.str_replace('/', '\/', $criterion->condition).'/', self::stringify($target)) === 1;
    }

    private function evaluateJsonPath(SuccessCriterion $criterion, mixed $responseBody, WorkflowContext $context, string $stepId, ?ArazzoDocument $document): bool
    {
        if ($criterion->context !== null) {
            try {
                $root = $this->evaluator->evaluate(new Expression($criterion->context), new EvaluationContext($context, $stepId, $document));
            } catch (\Throwable) {
                // Evaluation errors fail the criterion deterministically.
                return false;
            }
        } else {
            $root = $responseBody;
        }

        $result = JsonPathEvaluator::evaluate($criterion->condition, is_array($root) ? $root : []);

        return !empty($result);
    }

    private function hasOperationTarget(Step $step): bool
    {
        return $step->operationId !== null || $step->operationPath !== null;
    }

    private static function isSuccessStatusCode(mixed $statusCode): bool
    {
        if (!is_int($statusCode) && !is_string($statusCode)) {
            // No status code recorded yet (e.g. mocked transports); fall back to pass.
            return true;
        }

        $code = (int) $statusCode;

        return $code >= 200 && $code < 300;
    }

    private function evaluateXPath(SuccessCriterion $criterion, WorkflowContext $context, string $stepId, ?ArazzoDocument $document): bool
    {
        if ($criterion->context !== null) {
            try {
                $root = $this->evaluator->evaluate(new Expression($criterion->context), new EvaluationContext($context, $stepId, $document));
            } catch (\Throwable) {
                return false;
            }
        } else {
            $steps = $context->getSteps();
            $stepData = $steps[$stepId] ?? null;
            $response = is_array($stepData) ? ($stepData['response'] ?? null) : null;
            $root = is_array($response) ? ($response['body'] ?? []) : [];
        }

        $version = $criterion->version ?? 'xpath-10';

        try {
            $result = $this->xpath()->query($root, $criterion->condition, $version);
        } catch (\Throwable) {
            // Evaluation errors fail the criterion deterministically.
            return false;
        }

        return ConditionEvaluator::truthy($result);
    }

    private function xpath(): XpathEvaluator
    {
        return $this->xpathEvaluator ??= new DomXpathEvaluator();
    }

    private static function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value) && !array_is_list($value)) {
            // Non-list arrays stringify as JSON for regex matching convenience.
            try {
                return json_encode($value, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return '';
            }
        }

        if (is_array($value)) {
            return implode(',', array_map(self::stringify(...), $value));
        }

        return '';
    }
}
