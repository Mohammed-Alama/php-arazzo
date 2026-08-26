<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts;

use Alama\Arazzo\Execution\StepExecutionOutcome;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\State\WorkflowContext;

interface StepProtocolExecutorInterface
{
    public function supports(Step $step, ArazzoDocument $document): bool;

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome;
}
