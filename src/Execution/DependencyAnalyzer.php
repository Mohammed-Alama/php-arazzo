<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Step;

class DependencyAnalyzer
{
    /**
     * @param Step[] $allSteps
     *
     * @return Step[]
     */
    public function getRunnableSteps(array $allSteps, WorkflowContext $context): array
    {
        $runnable = [];

        foreach ($allSteps as $step) {
            $status = $context->getStepStatus($step->stepId);

            // A step is only runnable if it hasn't been executed yet (null) or has been reset (Pending)
            if ($status !== null && $status !== StepStatus::Pending) {
                continue;
            }

            // If no dependencies, it's runnable
            if (empty($step->dependsOn)) {
                $runnable[] = $step;

                continue;
            }

            // Check if all dependencies are completed (succeeded)
            $dependenciesMet = true;
            foreach ($step->dependsOn as $dependencyId) {
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
