<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Spec\Parameter;
use Alama\Arazzo\Spec\Reusable;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;

/**
 * Applies workflow-level parameters to a step before execution. A step-level
 * parameter with the same name and location overrides the workflow one, but
 * workflow parameters cannot be removed by steps (Arazzo Workflow Object).
 */
final class StepParameterMerger
{
    public static function merge(Step $step, ?Workflow $workflow): Step
    {
        if ($workflow === null || $workflow->parameters === []) {
            return $step;
        }

        $merged = $workflow->parameters;
        foreach ($step->parameters as $stepParam) {
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
            operationId: $step->operationId,
            operationPath: $step->operationPath,
            workflowId: $step->workflowId,
            parameters: $merged,
            requestBody: $step->requestBody,
            successCriteria: $step->successCriteria,
            onSuccess: $step->onSuccess,
            onFailure: $step->onFailure,
            outputs: $step->outputs,
            dependsOn: $step->dependsOn,
            action: $step->action,
            channelPath: $step->channelPath,
            correlationId: $step->correlationId,
            strictValidation: $step->strictValidation,
            idempotencyKey: $step->idempotencyKey,
            idempotencyHeader: $step->idempotencyHeader,
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
