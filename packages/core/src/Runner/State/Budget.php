<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\State;

final class Budget
{
    /**
     * @param list<string> $workflowCallStack
     */
    public function __construct(
        public readonly int $maxSteps,
        public readonly int $stepsSpent,
        public readonly int $maxWorkflowDepth,
        public readonly array $workflowCallStack,
    ) {
    }

    public function remainingSteps(): int
    {
        return max(0, $this->maxSteps - $this->stepsSpent);
    }

    public function currentDepth(): int
    {
        return count($this->workflowCallStack);
    }

    public function canEnterWorkflow(): bool
    {
        return $this->currentDepth() < $this->maxWorkflowDepth;
    }
}
