<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Plugins;

use Alama\Arazzo\Contracts\Interfaces\ExpressionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;

final readonly class JsonPathExpressionPlugin implements ExpressionEvaluatorPluginInterface
{
    public function __construct(
        private ExpressionEngineInterface $expression,
    ) {}

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
        $raw = $expression->raw;
        $sigil = $raw[0] ?? '';
        if ($sigil !== '$' && $sigil !== '@') {
            return false;
        }

        // A JSONPath root is followed by a member/index step (`.`, `[`) or
        // nothing at all. Every other `$…` spelling is an Arazzo runtime
        // reference (`$outputs.x`, `$statusCode`, `${steps.x}`, …): claiming
        // those would send them to jsonPath() and silently answer [].
        $next = $raw[1] ?? '';

        return $next === '' || $next === '.' || $next === '[';
    }

    /**
     * @param  mixed  $context  The data to query; a JSONPath expression over
     *                          anything that is not an array or an object cannot
     *                          match
     */
    public function evaluate(Expression $expression, mixed $context): mixed
    {
        // Mirrors JsonPathCriterionPlugin: a context that cannot be queried has no
        // matches, which is what jsonPath() answers for an empty collection.
        if (!is_array($context) && !is_object($context)) {
            return [];
        }

        return $this->expression->jsonPath($expression->raw, $context);
    }
}
