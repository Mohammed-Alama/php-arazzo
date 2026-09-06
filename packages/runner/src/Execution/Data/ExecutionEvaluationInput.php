<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution\Data;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Expression\ExpressionEngineInterface;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;

/**
 * Runner-owned evaluation input.
 *
 * The runner reaches the expression package through its public face
 * ({@see ExpressionEngineInterface}), whose
 * `evaluate` requires an {@see EvaluationInputInterface}. This value object
 * is the runner's own implementation of that cross-seam contract, so the
 * runner never touches the expression package's internal `EvaluationContext`.
 */
final readonly class ExecutionEvaluationInput implements EvaluationInputInterface
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
