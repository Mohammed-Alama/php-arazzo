<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Contracts;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\StepExecutionOutcome;
use Alama\LaravelArazzo\Execution\WorkflowContext;

interface StepProtocolExecutorInterface
{
    public function supports(Step $step, ArazzoDocument $document): bool;

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome;
}
