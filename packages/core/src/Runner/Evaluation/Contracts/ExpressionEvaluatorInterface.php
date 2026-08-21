<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation\Contracts;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Spec\Expression;

interface ExpressionEvaluatorInterface
{
    public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed;
}
