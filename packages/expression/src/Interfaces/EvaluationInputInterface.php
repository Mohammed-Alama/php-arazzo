<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Interfaces;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;

interface EvaluationInputInterface
{
    public function getWorkflowContext(): WorkflowContextInterface;

    public function getCurrentStepId(): ?string;

    public function getDocument(): ?ArazzoDocument;
}
