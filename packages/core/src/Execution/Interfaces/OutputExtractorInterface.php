<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution\Interfaces;

use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\WorkflowContext;

interface OutputExtractorInterface
{
    /**
     * @return array<string, mixed>
     */
    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array;
}
