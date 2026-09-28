<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Expression\Data\EvaluationContext;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;

/**
 * @internal stays out of the advertised contract; consumed by the ExpressionEngine facade.
 */
class StringInterpolator
{
    public function __construct(private ExpressionEngineInterface $expression) {}

    public function interpolate(string $value, WorkflowContextInterface $context, string $stepId): string
    {
        // Arazzo spells a runtime expression either `{$token}` or `${token}`;
        // both are captured here and handed to the evaluator in the canonical
        // `{$token}` form. A bare `$token` has no closing delimiter, so it is
        // only meaningful as a whole value and is never interpolated here.
        return preg_replace_callback('/(?:\{\$|\$\{)([^\}]+)\}/', function ($matches) use ($context, $stepId) {
            $expr = new Expression('{$'.$matches[1].'}');
            $ctx = new EvaluationContext($context, $stepId);
            $result = $this->expression->evaluate($expr, $ctx);
            if ($result === null) {
                return '';
            }

            return is_scalar($result) ? (string) $result : (string) json_encode($result);
        }, $value) ?? $value;
    }
}
