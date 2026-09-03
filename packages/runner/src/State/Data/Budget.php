<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\State\Data;

final readonly class Budget
{
    /**
     * @param  list<string>  $workflowCallStack
     */
    public function __construct(
        public int $maxSteps,
        public int $stepsSpent,
        public int $maxWorkflowDepth,
        public array $workflowCallStack,
    ) {}

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
