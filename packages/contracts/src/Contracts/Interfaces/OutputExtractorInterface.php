<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Spec\Step;

interface OutputExtractorInterface
{
    /**
     * @return array<string, mixed>
     */
    public function extractOutputs(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): array;
}
