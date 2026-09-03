<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;

/**
 * Entry-point seam for the expression package.
 *
 * Downstream packages depend on this interface (never the concrete
 * {@see ExpressionEngine} or the eval internals).
 */
interface ExpressionEngineInterface
{
    /**
     * Evaluate an Arazzo expression against a run context.
     */
    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed;
}
