<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Expression\ExpressionEngineInterface;
use Alama\Arazzo\Runner\Execution\Data\ExecutionEvaluationInput;

/**
 * Single resolution path for step-level runtime values (parameters,
 * payload replacements, header values) shared by every adapter so the
 * synchronous and queued execution paths cannot drift apart.
 *
 * Consumes the expression package exclusively through its public face
 * ({@see ExpressionEngineInterface}); no expression internals.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ExpressionValueResolver
{
    public function __construct(private readonly ExpressionEngineInterface $engine) {}

    public function resolve(mixed $value, WorkflowContext $context, ?string $stepId = null): mixed
    {
        if ($value instanceof Selector) {
            return $this->engine->evaluateSelector($value, $context, $stepId ?? '');
        }

        if ($value instanceof Expression) {
            return $this->engine->evaluate($value, new ExecutionEvaluationInput($context, $stepId));
        }

        if (!is_string($value)) {
            return $value;
        }

        if ($stepId === null) {
            $stepId = '';
        }

        if (str_contains($value, '{$')) {
            return $this->engine->interpolate($value, $context, $stepId);
        }

        // Arazzo values may use the bare runtime-expression spellings
        // (`$inputs.x`, `${inputs.x}`); normalize them into the
        // interpolator's `{$...}` template form before evaluation.
        if (preg_match('/^\$[{$]?[A-Za-z]/', $value) === 1 && !str_contains($value, ' ')) {
            return $this->engine->interpolate(
                $value[1] === '{' ? $value : '{'.$value.'}',
                $context,
                $stepId,
            );
        }

        return $value;
    }
}
