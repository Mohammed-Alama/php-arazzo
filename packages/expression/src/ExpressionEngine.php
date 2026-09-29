<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;

/**
 * Concrete expression facade: static parsing and reference projection, plus
 * runtime evaluation of expressions and selectors.
 */
final readonly class ExpressionEngine implements ExpressionEngineInterface
{
    public function __construct(
        private ExpressionDependencyProvider $provider = new ExpressionDependencyProvider(),
    ) {}

    public function parseExpression(string $raw): ?ExpressionSyntaxException
    {
        return $this->provider->getParser()->parseOrError($raw) instanceof ExpressionSyntaxException
            ? $this->provider->getParser()->parseOrError($raw)
            : null;
    }

    public function expressionReferences(string $raw): ?ExpressionReference
    {
        $result = $this->provider->getParser()->parseOrError($raw);

        return $result instanceof ExpressionSyntaxException ? null : $this->provider->getParser()->projectReferences($raw);
    }

    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
    {
        return $this->provider->getExpressionEvaluator()->evaluate($expression, $context);
    }

    public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed
    {
        return $this->provider->getSelectorEvaluator()->evaluate($selector, $context, $stepId);
    }

    public function jsonPath(string $expression, array|object $data): mixed
    {
        return $this->provider->getJsonPathEvaluator()->evaluate($expression, $data);
    }

    public function queryXPath(mixed $rootValue, string $selector, string $version): mixed
    {
        return $this->provider->getXpathEvaluator()->query($rootValue, $selector, $version);
    }

    public function supportedXPathVersions(): array
    {
        return $this->provider->getXpathEvaluator()->supportedVersions();
    }
}
