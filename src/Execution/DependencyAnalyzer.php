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
        $completedStepIds = array_keys($context->getSteps());

        foreach ($allSteps as $step) {
            // If already completed, skip
            if (in_array($step->stepId, $completedStepIds, true)) {
                continue;
            }

            // If no dependencies, it's runnable
            if (empty($step->dependsOn)) {
                $runnable[] = $step;

                continue;
            }

            // Check if all dependencies are completed
            $dependenciesMet = true;
            foreach ($step->dependsOn as $dependencyId) {
                if (!in_array($dependencyId, $completedStepIds, true)) {
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
