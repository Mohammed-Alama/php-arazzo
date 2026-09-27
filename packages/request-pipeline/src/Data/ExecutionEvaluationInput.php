<?php

declare(strict_types=1);

namespace Alama\Arazzo\RequestPipeline\Data;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Evaluation\Interfaces\EvaluationInputInterface;

/**
 * Request-pipeline-owned evaluation input.
 *
 * The pipeline reaches the expression package through its public face
 * ({@see EvaluationEngineInterface}), whose
 * `evaluate` requires an {@see EvaluationInputInterface}. This value object
 * is the pipeline's own implementation of that cross-seam contract, so the
 * pipeline never touches the expression package's internal `EvaluationContext`.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
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
