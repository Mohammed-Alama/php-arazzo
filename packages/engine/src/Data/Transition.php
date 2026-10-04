<?php

declare(strict_types=1);

namespace Alama\Arazzo\Engine\Data;

use Alama\Arazzo\Contracts\State\ExecutionState;

/**
 * Workflow-level state transition result.
 */
final readonly class Transition
{
    public function __construct(
        public ExecutionState $state,
        public string $status,
        public ?string $workflowId = null,
    ) {}
}
