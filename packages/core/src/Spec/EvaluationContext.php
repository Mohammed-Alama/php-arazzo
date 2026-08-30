<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec;

final readonly class EvaluationContext
{
    public function __construct(
        public readonly WorkflowContext $workflowContext,
        public readonly ?string $currentStepId = null,
        public readonly ?ArazzoDocument $document = null,
    ) {}
}
