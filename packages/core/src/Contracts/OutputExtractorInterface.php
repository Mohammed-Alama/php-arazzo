<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Step;

interface OutputExtractorInterface
{
    /**
     * @return array<string, mixed>
     */
    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array;
}
