<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution\Contracts;

use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Execution\WorkflowContext;

interface ExpressionEvaluatorInterface
{
    public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed;
}
