<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\Condition\ConditionEvaluator;
use Alama\Arazzo\Runner\Evaluation\Condition\ConditionSyntaxException;
use Alama\Arazzo\Runner\Evaluation\Contracts\CriteriaEvaluatorInterface;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionEvaluatorInterface;
use Alama\Arazzo\Runner\Exceptions\UnsupportedCriterionTypeException;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\CriterionType;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\SuccessCriterion;

class ArazzoCriteriaEvaluator implements CriteriaEvaluatorInterface
{
    private ConditionEvaluator $conditionEvaluator;

    public function __construct(
        private ExpressionEvaluatorInterface $evaluator,
        ?ConditionEvaluator $conditionEvaluator = null,
    ) {
        $this->conditionEvaluator = $conditionEvaluator ?? new ConditionEvaluator($evaluator);
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
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

            throw new UnsupportedCriterionTypeException("Criterion type '{$type->value}' is not supported.");
        }

        return true;
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
