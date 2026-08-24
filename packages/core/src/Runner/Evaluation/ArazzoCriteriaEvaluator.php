<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\Condition\ConditionEvaluator;
use Alama\Arazzo\Runner\Evaluation\Condition\ConditionSyntaxException;
use Alama\Arazzo\Runner\Evaluation\Contracts\CriteriaEvaluatorInterface;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionEvaluatorInterface;
use Alama\Arazzo\Runner\Evaluation\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Runner\Evaluation\Xpath\XpathEvaluator;
use Alama\Arazzo\Runner\Exceptions\UnsupportedCriterionTypeException;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\CriterionType;
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
            return self::isSuccessStatusCode($context->getSteps()[$step->stepId]['response']['statusCode'] ?? null);
        }

        return $this->evaluateCriteria($step->successCriteria, $step, $context, $document);
    }

    /**
     * @param list<SuccessCriterion> $criteria
     */
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        if (empty($criteria)) {
            return true;
        }

        $responseBody = $context->getSteps()[$step->stepId]['response']['body'] ?? [];

        foreach ($criteria as $criterion) {
            $type = $criterion->type ?? CriterionType::Simple;

            if ($type === CriterionType::Simple) {
                try {
                    $passed = $this->conditionEvaluator->evaluate($criterion->condition, $context, $step->stepId, $document);
                } catch (ConditionSyntaxException) {
                    // Evaluation errors fail the criterion deterministically.
                    return false;
                }

                if (!$passed) {
                    return false;
                }

                continue;
            }

            if ($type === CriterionType::Regex) {
                if ($criterion->context === null) {
                    // A regex criterion without a context cannot be evaluated; fail deterministically.
                    return false;
                }

                $target = $this->evaluator->evaluate(new Expression($criterion->context), new EvaluationContext($context, $step->stepId, $document));
                if ($target === null) {
                    return false;
                }

                if (!preg_match('/' . str_replace('/', '\/', $criterion->condition) . '/', self::stringify($target))) {
                    return false;
                }

                continue;
            }

            if ($type === CriterionType::JsonPath) {
                if ($criterion->context !== null) {
                    try {
                        $root = $this->evaluator->evaluate(new Expression($criterion->context), new EvaluationContext($context, $step->stepId, $document));
                    } catch (\Throwable) {
                        // Evaluation errors fail the criterion deterministically.
                        return false;
                    }
                } else {
                    $root = $responseBody;
                }

                $result = JsonPathEvaluator::evaluate($criterion->condition, is_array($root) ? $root : []);
                if (empty($result)) {
                    return false;
                }

                continue;
            }

            if ($type === CriterionType::XPath) {
                $passed = $this->evaluateXPath($criterion, $context, $step->stepId, $document);
                if (!$passed) {
                    return false;
                }

                continue;
            }

            throw new UnsupportedCriterionTypeException("Criterion type '{$type->value}' is not supported.");
        }

        return true;
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
            $root = $context->getSteps()[$stepId]['response']['body'] ?? [];
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
