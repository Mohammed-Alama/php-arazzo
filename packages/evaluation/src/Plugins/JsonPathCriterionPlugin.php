<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Plugins;

use Alama\Arazzo\Contracts\Interfaces\CriterionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;

final readonly class JsonPathCriterionPlugin implements CriterionEvaluatorPluginInterface
{
    public function name(): string
    {
        return 'jsonpath-criterion';
    }

    public function priority(): int
    {
        return 0;
    }

    public function supports(CriterionType|SuccessCriterion $criterion): bool
    {
        if ($criterion instanceof CriterionType) {
            return $criterion === CriterionType::JsonPath;
        }
        return $criterion->type === CriterionType::JsonPath;
    }

    public function evaluate(SuccessCriterion $criterion, mixed $context, Step $step, WorkflowContextInterface $workflowContext): bool
    {
        // $context is the response body (mixed). Delegate to existing evaluator logic.
        $result = \Alama\Arazzo\Evaluation\JsonPathEvaluator::evaluate($criterion->condition, $context);

        // Truthiness: non-empty array or non-false/non-null scalar
        if (is_array($result)) {
            return $result !== [];
        }
        return (bool) $result;
    }
}