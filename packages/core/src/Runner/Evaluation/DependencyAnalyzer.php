<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Spec\Enum\StepStatus;
use Alama\Arazzo\Spec\Step;

class DependencyAnalyzer
{
    public function __construct(
        private DependencyGraph $graph,
    ) {}

    /**
     * @return Step[]
     */
    public function getRunnableSteps(WorkflowContext $context): array
    {
        $runnable = [];

        foreach ($this->graph->getTopologicalOrder() as $stepId) {
            $step = $this->graph->getStepsById()[$stepId];
            $status = $context->getStepStatus($step->stepId);

            // A step is only runnable if it hasn't been executed yet (null) or has been reset (Pending)
            if ($status !== null && $status !== StepStatus::Pending) {
                continue;
            }

            $dependencies = $this->graph->getEffectiveDependencies($step->stepId);

            // If no dependencies, it's runnable
            if ($dependencies === []) {
                $runnable[] = $step;

                continue;
            }

            // Check if all dependencies are completed (succeeded)
            $dependenciesMet = true;
            foreach ($dependencies as $dependencyId) {
                if ($context->getStepStatus($dependencyId) !== StepStatus::Succeeded) {
                    $dependenciesMet = false;
                    break;
                }
            }

            if ($dependenciesMet) {
                $runnable[] = $step;
            }
        }

        return $runnable;
    }
}
