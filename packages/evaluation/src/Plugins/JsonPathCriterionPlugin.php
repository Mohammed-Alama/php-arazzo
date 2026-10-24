<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Plugins;

use Alama\Arazzo\Contracts\Interfaces\CriterionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;

final readonly class JsonPathCriterionPlugin implements CriterionEvaluatorPluginInterface
{
    public function __construct(
        private ExpressionEngineInterface $expression,
    ) {}

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
        // $context is the root the criterion applies to: the resolved `context`
        // expression when the criterion declares one, otherwise the response
        // body. It need not be structured: a JSONPath query over anything else
        // cannot match, the same answer jsonPath() gives for an empty collection.
        if (!is_array($context) && !is_object($context)) {
            return false;
        }

        // Delegate to the public expression API.
        $result = $this->expression->jsonPath($criterion->condition, $context);

        // Truthiness: non-empty array or non-false/non-null scalar
        if (is_array($result)) {
            return $result !== [];
        }

        return (bool) $result;
    }
}
