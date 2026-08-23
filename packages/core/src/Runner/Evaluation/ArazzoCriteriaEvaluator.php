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
                    continue; // Skip invalid regex criteria
                }
                $target = $this->evaluator->evaluate(new Expression($criterion->context), new EvaluationContext($context, $step->stepId, $document));
                if (!preg_match('/' . str_replace('/', '\/', $criterion->condition) . '/', (string) $target)) {
                    return false;
                }

                continue;
            }

            if ($type === CriterionType::JsonPath) {
                $result = JsonPathEvaluator::evaluate($criterion->condition, is_array($responseBody) ? $responseBody : []);
                if (empty($result)) {
                    return false;
                }

                continue;
            }

            throw new UnsupportedCriterionTypeException("Criterion type '{$type->value}' is not supported.");
        }

        return true;
    }
}
