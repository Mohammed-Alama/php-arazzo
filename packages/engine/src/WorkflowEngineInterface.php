<?php

declare(strict_types=1);

namespace Alama\Arazzo\Engine;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Engine\Data\Transition;

/**
 * Interface for workflow-level state transitions.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface WorkflowEngineInterface
{
    public function transition(
        ArazzoDocument $document,
        Workflow $workflow,
        Step $step,
        ExecutionState $state,
        bool $criteriaMet,
    ): Transition;
}
