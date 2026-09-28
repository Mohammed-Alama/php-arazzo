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
use Alama\Arazzo\Expression\Parser as ExpressionParser;
use Alama\Arazzo\Expression\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Expression\Xpath\XpathEvaluator;

/**
 * Concrete expression facade: static parsing and reference projection, plus
 * runtime evaluation of expressions and selectors.
 */
final readonly class ExpressionEngine implements ExpressionEngineInterface
{
    private ExpressionParser $parser;

    private ExpressionEvaluator $evaluator;

    private SelectorEvaluator $selectors;

    private XpathEvaluator $xpath;

    public function __construct(
        ?ExpressionParser $parser = null,
        ?ExpressionEvaluator $evaluator = null,
        ?SelectorEvaluator $selectors = null,
        ?XpathEvaluator $xpath = null,
    ) {
        $this->parser = $parser ?? new ExpressionParser();
        $this->evaluator = $evaluator ?? new ExpressionEvaluator();
        $this->xpath = $xpath ?? new DomXpathEvaluator();
        $this->selectors = $selectors ?? new SelectorEvaluator($this->xpath, $this->evaluator);
    }

    public function parseExpression(string $raw): ?ExpressionSyntaxException
    {
        $result = $this->parser->parseOrError($raw);

        return $result instanceof ExpressionSyntaxException ? $result : null;
    }

    public function expressionReferences(string $raw): ?ExpressionReference
    {
        return $this->parser->parseOrError($raw) instanceof ExpressionSyntaxException
            ? null
            : $this->parser->projectReferences($raw);
    }

    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
    {
        return $this->evaluator->evaluate($expression, $context);
    }

    public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed
    {
        return $this->selectors->evaluate($selector, $context, $stepId);
    }

    public function jsonPath(string $expression, array|object $data): mixed
    {
        return JsonPathEvaluator::evaluate($expression, $data);
    }

    public function queryXPath(mixed $rootValue, string $selector, string $version): mixed
    {
        return $this->xpath->query($rootValue, $selector, $version);
    }

    public function supportedXPathVersions(): array
    {
        return $this->xpath->supportedVersions();
    }
}
