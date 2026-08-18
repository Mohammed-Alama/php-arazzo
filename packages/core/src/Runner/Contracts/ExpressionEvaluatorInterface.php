<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Contracts;

use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Runner\WorkflowContext;

interface ExpressionEvaluatorInterface
{
    public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed;
}
