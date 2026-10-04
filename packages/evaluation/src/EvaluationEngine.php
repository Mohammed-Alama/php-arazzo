<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\Registries\CriterionEvaluatorRegistry;
use Alama\Arazzo\Evaluation\Registries\ExpressionEvaluatorRegistry;
use Alama\Arazzo\Expression\Data\EvaluationContext;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;

/**
 * Concrete evaluation facade.
 *
 * Hides runtime evaluation collaborators (criteria evaluator, string interpolator,
 * payload replacer, expression engine) behind a single entry point.
 */
final class EvaluationEngine implements EvaluationEngineInterface
{
    private readonly ExpressionEvaluatorRegistry $expressionRegistry;

    private readonly CriterionEvaluatorRegistry $criterionRegistry;

    public function __construct(
        private readonly ExpressionEngineInterface $expression,
        ?ExpressionEvaluatorRegistry $expressionRegistry = null,
        ?CriterionEvaluatorRegistry $criterionRegistry = null,
    ) {
        $this->expressionRegistry = $expressionRegistry ?? new ExpressionEvaluatorRegistry($this->expression);
        $this->criterionRegistry = $criterionRegistry ?? new CriterionEvaluatorRegistry($this->expression);
    }

    private ?CriteriaEvaluator $criteriaEvaluator = null;

    private ?StringInterpolator $interpolator = null;

    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
    {
        $plugin = $this->expressionRegistry->resolve($expression);
        if ($plugin !== null) {
            return $plugin->evaluate($expression, $context);
        }

        return $this->expression->evaluate($expression, $context);
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return $this->criteria()->evaluateCriteria($criteria, $step, $context, $document);
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return $this->criteria()->evaluateSuccessCriteria($step, $context, $document);
    }

    public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed
    {
        return $this->expression->evaluateSelector($selector, $context, $stepId);
    }

    public function queryXPath(mixed $rootValue, string $selector, string $version): mixed
    {
        return $this->expression->queryXPath($rootValue, $selector, $version);
    }

    public function supportedXPathVersions(): array
    {
        return $this->expression->supportedXPathVersions();
    }

    public function resolveValue(mixed $value, WorkflowContextInterface $context, ?string $stepId = null): mixed
    {
        if ($value instanceof Selector) {
            return $this->evaluateSelector($value, $context, $stepId ?? '');
        }

        if ($value instanceof Expression) {
            return $this->evaluate($value, new EvaluationContext($context, $stepId));
        }

        if (!is_string($value)) {
            return $value;
        }

        if ($stepId === null) {
            $stepId = '';
        }

        // Arazzo values may spell a runtime expression either `{$...}` or
        // `${...}`, anywhere in the string; the interpolator understands both.
        if (str_contains($value, '{$') || str_contains($value, '${')) {
            return $this->interpolate($value, $context, $stepId);
        }

        // A whole value may instead be a bare runtime expression (`$inputs.x`).
        // A bare `$` carries no closing delimiter, so it is only meaningful
        // when it is the entire value.
        if (preg_match('/^\$[A-Za-z]/', $value) === 1 && !str_contains($value, ' ')) {
            return $this->interpolate('{'.$value.'}', $context, $stepId);
        }

        return $value;
    }

    public function interpolate(string $value, WorkflowContextInterface $context, string $stepId): string
    {
        return $this->interpolator()->interpolate($value, $context, $stepId);
    }

    public function replacePayload(Step $step, array $body, ?callable $resolveValue = null, ?WorkflowContext $context = null): array
    {
        return PayloadReplacer::apply($this->expression, $step, $body, $resolveValue, $context);
    }

    public function jsonPath(string $expression, array|object $data): mixed
    {
        return $this->expression->jsonPath($expression, $data);
    }

    private function criteria(): CriteriaEvaluator
    {
        return $this->criteriaEvaluator ??= new CriteriaEvaluator($this->expression, null, $this->criterionRegistry);
    }

    private function interpolator(): StringInterpolator
    {
        return $this->interpolator ??= new StringInterpolator($this->expression);
    }
}
