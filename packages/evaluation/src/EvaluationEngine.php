<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Evaluation\Registries\CriterionEvaluatorRegistry;
use Alama\Arazzo\Evaluation\Registries\ExpressionEvaluatorRegistry;
use Alama\Arazzo\Evaluation\Xpath\DomXpathEvaluator;

/**
 * Concrete evaluation facade.
 *
 * Hides runtime evaluation collaborators (expression evaluator, criteria evaluator,
 * selector evaluator, string interpolator, payload replacer) behind a single entry point.
 */
final class EvaluationEngine implements EvaluationEngineInterface
{
    public function __construct(
        private readonly ExpressionEvaluator $evaluator = new ExpressionEvaluator(),
        private readonly DomXpathEvaluator $xpath = new DomXpathEvaluator(),
        private readonly ExpressionEvaluatorRegistry $expressionRegistry = new ExpressionEvaluatorRegistry(),
        private readonly CriterionEvaluatorRegistry $criterionRegistry = new CriterionEvaluatorRegistry(),
    ) {}

    private ?CriteriaEvaluator $criteriaEvaluator = null;

    private ?SelectorEvaluator $selectorEvaluator = null;

    private ?StringInterpolator $interpolator = null;

    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
    {
        $plugin = $this->expressionRegistry->resolve($expression);
        if ($plugin !== null) {
            return $plugin->evaluate($expression, $context);
        }

        return $this->evaluator->evaluate($expression, $context);
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
        return $this->selectors()->evaluate($selector, $context, $stepId);
    }

    public function queryXPath(mixed $rootValue, string $selector, string $version): mixed
    {
        return $this->xpath->query($rootValue, $selector, $version);
    }

    public function supportedXPathVersions(): array
    {
        return $this->xpath->supportedVersions();
    }

    public function interpolate(string $value, WorkflowContextInterface $context, string $stepId): string
    {
        return $this->interpolator()->interpolate($value, $context, $stepId);
    }

    public function replacePayload(Step $step, array $body, ?callable $resolveValue = null, ?WorkflowContext $context = null): array
    {
        return PayloadReplacer::apply($step, $body, $resolveValue, $context);
    }

    public function jsonPath(string $expression, array|object $data): mixed
    {
        return JsonPathEvaluator::evaluate($expression, $data);
    }

    public function jsonPointer(array $data, ?string $pointer): mixed
    {
        return JsonPointer::resolve($data, $pointer);
    }

    private function criteria(): CriteriaEvaluator
    {
        return $this->criteriaEvaluator ??= new CriteriaEvaluator($this->evaluator, null, null, $this->criterionRegistry);
    }

    private function selectors(): SelectorEvaluator
    {
        return $this->selectorEvaluator ??= new SelectorEvaluator($this->xpath, $this->evaluator);
    }

    private function interpolator(): StringInterpolator
    {
        return $this->interpolator ??= new StringInterpolator(new InterpolationResolver($this->evaluator));
    }
}
