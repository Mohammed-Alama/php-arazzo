<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Data;

use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Interfaces\WorkflowContextInterface;

final readonly class EvaluationContext implements EvaluationInputInterface
{
    public function __construct(
        public WorkflowContextInterface $workflowContext,
        public ?string $currentStepId = null,
        public ?ArazzoDocument $document = null,
    ) {}

    public function getWorkflowContext(): WorkflowContextInterface
    {
        return $this->workflowContext;
    }

    public function getCurrentStepId(): ?string
    {
        return $this->currentStepId;
    }

    public function getDocument(): ?ArazzoDocument
    {
        return $this->document;
    }
}
