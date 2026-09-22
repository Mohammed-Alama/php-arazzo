<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\Workflow;

/**
 * Applies workflow-level parameters to a step before execution. A step-level
 * parameter with the same name and location overrides the workflow one, but
 * workflow parameters cannot be removed by steps (Arazzo Workflow Object).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class StepParameterMerger
{
    public static function merge(Step $step, ?Workflow $workflow): Step
    {
        if ($workflow === null || $workflow->parameters === []) {
            return $step;
        }

        $merged = $workflow->parameters;
        foreach ($step->io->parameters as $stepParam) {
            $replaced = false;
            foreach ($merged as $i => $base) {
                if (self::sameTarget($base, $stepParam)) {
                    $merged[$i] = $stepParam;
                    $replaced = true;

                    break;
                }
            }

            if (!$replaced) {
                $merged[] = $stepParam;
            }
        }

        return new Step(
            stepId: $step->stepId,
            description: $step->description,
            target: $step->target,
            flow: $step->flow,
            io: new StepIo(
                parameters: $merged,
                requestBody: $step->io->requestBody,
                successCriteria: $step->io->successCriteria,
                outputs: $step->io->outputs,
            ),
        );
    }

    private static function sameTarget(Parameter|Reusable $a, Parameter|Reusable $b): bool
    {
        if ($a instanceof Reusable || $b instanceof Reusable) {
            return false; // reusables never collide by name/location at merge time
        }

        return $a->name === $b->name && ($a->in ?? null) === ($b->in ?? null);
    }
}
