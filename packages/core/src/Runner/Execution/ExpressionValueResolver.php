<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Evaluation\StringInterpolator;
use Alama\Arazzo\Spec\Expression;

/**
 * Single resolution path for step-level runtime values (parameters,
 * payload replacements, header values) shared by every adapter so the
 * synchronous and queued execution paths cannot drift apart.
 */
final class ExpressionValueResolver
{
    private StringInterpolator $interpolator;

    public function __construct(private readonly ExpressionResolverInterface $expressions)
    {
        $this->interpolator = new StringInterpolator($this->expressions);
    }

    public function resolve(mixed $value, WorkflowContext $context, ?string $stepId = null): mixed
    {
        if ($value instanceof Expression) {
            return $stepId === null
                ? $this->expressions->evaluate($value, $context)
                : $this->expressions->evaluate($value, $context, $stepId);
        }

        if (!is_string($value)) {
            return $value;
        }

        if ($stepId === null) {
            $stepId = '';
        }

        if (str_contains($value, '{$')) {
            return $this->interpolator->interpolate($value, $context, $stepId);
        }

        // Arazzo values may use the bare runtime-expression spellings
        // (`$inputs.x`, `${inputs.x}`); normalize them into the
        // interpolator's `{$...}` template form before evaluation.
        if (preg_match('/^\$[{$]?[A-Za-z]/', $value) === 1 && !str_contains($value, ' ')) {
            return $this->interpolator->interpolate(
                $value[1] === '{' ? $value : '{' . $value . '}',
                $context,
                $stepId,
            );
        }

        return $value;
    }
}
