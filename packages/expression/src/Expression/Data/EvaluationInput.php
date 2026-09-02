<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Data;

use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Interfaces\WorkflowContextInterface;

final readonly class EvaluationInput implements EvaluationInputInterface
{
    public function __construct(
        private WorkflowContextInterface $workflowContext,
        private ?string $currentStepId = null,
        private ?ArazzoDocument $document = null,
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
