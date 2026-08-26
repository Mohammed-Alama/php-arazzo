<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\State\WorkflowContext;

final class EvaluationContext
{
    public function __construct(
        public readonly WorkflowContext $workflowContext,
        public readonly ?string $currentStepId = null,
        public readonly ?ArazzoDocument $document = null,
    ) {}
}
