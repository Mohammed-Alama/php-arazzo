<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Plugins;

use Alama\Arazzo\Contracts\Interfaces\ExpressionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Spec\Expression;

final readonly class JsonPathExpressionPlugin implements ExpressionEvaluatorPluginInterface
{
    public function name(): string
    {
        return 'jsonpath-expression';
    }

    public function priority(): int
    {
        return 0;
    }

    public function supports(Expression $expression): bool
    {
        // Very lightweight heuristic: JSONPath expressions start with '$' or '@'
        $raw = $expression->raw;
        return $raw !== '' && ($raw[0] === '$' || $raw[0] === '@');
    }

    /**
     * @param mixed $context Expected to be an array|object that JSONPath can query
     */
    public function evaluate(Expression $expression, mixed $context): mixed
    {
        return \Alama\Arazzo\Evaluation\JsonPathEvaluator::evaluate($expression->raw, $context);
    }
}