<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;

/**
 * Extracts step outputs from a workflow execution context.
 *
 * The concrete `StepOutputExtractor` in alama/arazzo-protocol-http implements
 * this port. Runner-side code depends on this interface, not the concrete
 * transport-bound extractor.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface OutputExtractorInterface
{
    /**
     * @return array<string, mixed>
     */
    public function extractOutputs(
        Step $step,
        WorkflowContextInterface $context,
        ?ArazzoDocument $document = null,
    ): array;
}
