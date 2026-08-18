<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Contracts;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Runner\WorkflowContext;

interface OutputExtractorInterface
{
    /**
     * @return array<string, mixed>
     */
    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array;
}
