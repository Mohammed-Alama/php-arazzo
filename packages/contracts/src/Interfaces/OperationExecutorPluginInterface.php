<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Contracts\State\WorkflowContext;

/**
 * Executes an Arazzo Step's Operation for one protocol.
 *
 * Replaces StepProtocolExecutorInterface. Existing executors implement
 * this by typecasting the context: StepProtocolExecutor executes against
 * the concrete WorkflowContext; this face keeps the same shape (spec D1).
 */
interface OperationExecutorPluginInterface extends PluginInterface
{
    public function supports(Step $step, ArazzoDocument $document): bool;

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome;
}
