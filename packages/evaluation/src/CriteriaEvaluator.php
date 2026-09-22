<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Evaluation\Condition\ConditionEvaluator;
use Alama\Arazzo\Evaluation\Condition\ConditionSyntaxException;
use Alama\Arazzo\Evaluation\Data\EvaluationContext;
use Alama\Arazzo\Evaluation\Interfaces\CriteriaEvaluatorInterface;
use Alama\Arazzo\Evaluation\Interfaces\ExpressionEvaluatorInterface;
use Alama\Arazzo\Evaluation\Registries\CriterionEvaluatorRegistry;
use Alama\Arazzo\Evaluation\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Evaluation\Xpath\XpathEvaluator;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
class CriteriaEvaluator implements CriteriaEvaluatorInterface
{
    private ConditionEvaluator $conditionEvaluator;

    private ?XpathEvaluator $xpathEvaluator;

    private ?CriterionEvaluatorRegistry $criterionRegistry;

    public function __construct(
        private ExpressionEvaluatorInterface $evaluator,
        ?ConditionEvaluator $conditionEvaluator = null,
        ?XpathEvaluator $xpathEvaluator = null,
        ?CriterionEvaluatorRegistry $criterionRegistry = null,
    ) {
        $this->conditionEvaluator = $conditionEvaluator ?? new ConditionEvaluator($evaluator);
        $this->xpathEvaluator = $xpathEvaluator;
        $this->criterionRegistry = $criterionRegistry;
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        // Default success behavior: an operation step without explicit criteria
        // passes on any 2xx response (community/spec-tooling convention).
        if ($this->hasOperationTarget($step) && $step->io->successCriteria === []) {
            $steps = $context->getSteps();
            $stepData = $steps[$step->stepId] ?? null;
            $response = is_array($stepData) ? ($stepData['response'] ?? null) : null;

            return self::isSuccessStatusCode(is_array($response) ? ($response['statusCode'] ?? null) : null);
        }

        return $this->evaluateCriteria($step->io->successCriteria, $step, $context, $document);
    }

    /**
     * @param  list<SuccessCriterion>  $criteria
     */
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
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

            // If we have a registry and it has a plugin for this criterion, delegate.
            if ($this->criterionRegistry !== null) {
                $plugin = $this->criterionRegistry->resolve($criterion);
                if ($plugin !== null) {
                    $passed = $plugin->evaluate($criterion, $responseBody, $step, $context);
                    if (!$passed) {
                        return false;
                    }

                    continue;
                }
            }

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

    private function evaluateSimple(SuccessCriterion $criterion, WorkflowContextInterface $context, string $stepId, ?ArazzoDocument $document): bool
    {
        try {
            return $this->conditionEvaluator->evaluate($criterion->condition, $context, $stepId, $document);
        } catch (ConditionSyntaxException) {
            // Evaluation errors fail the criterion deterministically.
            return false;
        }
    }

    private function evaluateRegex(SuccessCriterion $criterion, WorkflowContextInterface $context, string $stepId, ?ArazzoDocument $document): bool
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

    private function evaluateJsonPath(SuccessCriterion $criterion, mixed $responseBody, WorkflowContextInterface $context, string $stepId, ?ArazzoDocument $document): bool
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
        return $step->target->operationId !== null || $step->target->operationPath !== null;
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

    private function evaluateXPath(SuccessCriterion $criterion, WorkflowContextInterface $context, string $stepId, ?ArazzoDocument $document): bool
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
