<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec;

use Alama\Arazzo\Execution\Data\WorkflowContext;

final readonly class EvaluationContext
{
    public function __construct(
        public WorkflowContext $workflowContext,
        public ?string $currentStepId = null,
        public ?ArazzoDocument $document = null,
    ) {}
}
