<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;

/**
 * Concrete expression facade.
 *
 * A thin, self-contained object that hides the expression engine internals
 * (lexer/AST, evaluator, jsonpath/xpath) behind a single entry point.
 */
final class ExpressionEngine implements ExpressionEngineInterface
{
    public function __construct(
        private readonly ExpressionEvaluator $evaluator = new ExpressionEvaluator(),
    ) {}

    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
    {
        return $this->evaluator->evaluate($expression, $context);
    }
}
