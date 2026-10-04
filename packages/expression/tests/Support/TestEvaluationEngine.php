<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression\Support;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\Data\EvaluationContext;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;

final class TestEvaluationEngine implements EvaluationEngineInterface
{
    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
    {
        return true;
    }

    /**
     * @param  list<SuccessCriterion>  $criteria
     */
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return $criteria === [];
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed
    {
        return null;
    }

    public function queryXPath(mixed $rootValue, string $selector, string $version): mixed
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function supportedXPathVersions(): array
    {
        return [];
    }

    public function interpolate(string $value, WorkflowContextInterface $context, string $stepId): string
    {
        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed>
     */
    public function resolveValue(mixed $value, WorkflowContextInterface $context, ?string $stepId = null): mixed
    {
        if (is_string($value)) {
            return $this->interpolate($value, $context, $stepId ?? '');
        }

        if ($value instanceof Expression) {
            return $this->evaluate($value, new EvaluationContext($context, $stepId));
        }

        if ($value instanceof Selector) {
            return $this->evaluateSelector($value, $context, $stepId ?? '');
        }

        return $value;
    }

    public function replacePayload(Step $step, array $body, ?callable $resolveValue = null, ?WorkflowContext $context = null): array
    {
        return $body;
    }

    /**
     * @param  array<array-key, mixed>|object  $data
     */
    public function jsonPath(string $expression, array|object $data): mixed
    {
        return null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function jsonPointer(array $data, ?string $pointer): mixed
    {
        return null;
    }
}
