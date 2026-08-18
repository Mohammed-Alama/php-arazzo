<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution\Contracts;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Execution\WorkflowContext;

interface OutputExtractorInterface
{
    /**
     * @return array<string, mixed>
     */
    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array;
}
