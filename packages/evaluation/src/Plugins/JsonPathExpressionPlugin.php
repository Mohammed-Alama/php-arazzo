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
        if ($raw === '' || ($raw[0] !== '$' && $raw[0] !== '@')) {
            return false;
        }

        // `${...}` is the Arazzo runtime-expression wrapper, never JSONPath;
        // without this the braced spelling is claimed here and blows up in the
        // JSONPath lexer instead of reaching the Arazzo parser.
        if (str_starts_with($raw, '${')) {
            return false;
        }

        // Exclude Arazzo-specific expression patterns that start with $
        // but are not valid JSONPath (e.g., $response.body#/path, $steps.x.outputs.y)
        if (preg_match('/^\$ (?:inputs|steps|response|request|workflows)\./x', $raw)) {
            return false;
        }

        return true;
    }

    /**
     * @param  mixed  $context  Expected to be an array|object that JSONPath can query
     */
    public function evaluate(Expression $expression, mixed $context): mixed
    {
        return $this->expression->jsonPath($expression->raw, $context);
    }
}
