<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Contracts\WorkflowContext;
use Alama\Arazzo\Spec\ArazzoDocument;

final class EvaluationContext
{
    public function __construct(
        public readonly WorkflowContext $workflowContext,
        public readonly ?string $currentStepId = null,
        public readonly ?ArazzoDocument $document = null,
    ) {}
}
