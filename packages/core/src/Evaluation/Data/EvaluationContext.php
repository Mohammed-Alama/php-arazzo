<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Data;

use Alama\Arazzo\Execution\Data\WorkflowContext;
use Alama\Arazzo\Spec\ArazzoDocument;

final readonly class EvaluationContext
{
    public function __construct(
        public WorkflowContext $workflowContext,
        public ?string $currentStepId = null,
        public ?ArazzoDocument $document = null,
    ) {}
}
