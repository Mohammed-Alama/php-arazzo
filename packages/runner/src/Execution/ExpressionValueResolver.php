<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\Data\EvaluationContext;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;

/**
 * Single resolution path for step-level runtime values (parameters,
 * payload replacements, header values) shared by every adapter so the
 * synchronous and queued execution paths cannot drift apart.
 *
 * Consumes the expression package exclusively through its public face
 * ({@see EvaluationEngineInterface}); no expression internals.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ExpressionValueResolver
{
    public function __construct(private readonly EvaluationEngineInterface $engine) {}

    public function resolve(mixed $value, WorkflowContext $context, ?string $stepId = null): mixed
    {
        if ($value instanceof Selector) {
            return $this->engine->evaluateSelector($value, $context, $stepId ?? '');
        }

        if ($value instanceof Expression) {
            return $this->engine->evaluate($value, new EvaluationContext($context, $stepId));
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
            return $this->engine->interpolate($value, $context, $stepId);
        }

        // A whole value may instead be a bare runtime expression (`$inputs.x`).
        // A bare `$` carries no closing delimiter, so it is only meaningful
        // when it is the entire value.
        if (preg_match('/^\$[A-Za-z]/', $value) === 1 && !str_contains($value, ' ')) {
            return $this->engine->interpolate('{'.$value.'}', $context, $stepId);
        }

        return $value;
    }
}
