<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;

interface CriterionEvaluatorPluginInterface extends PluginInterface
{
    public function supports(CriterionType|SuccessCriterion $criterion): bool;

    /**
     * @param  SuccessCriterion  $criterion  The criterion being evaluated.
     * @param  mixed  $context  The root the criterion applies to: the resolved
     *                          `context` expression when the criterion declares
     *                          one, otherwise the step's response body.
     * @param  Step  $step  The step that produced the response.
     * @param  WorkflowContextInterface  $workflowContext  The workflow state.
     */
    public function evaluate(SuccessCriterion $criterion, mixed $context, Step $step, WorkflowContextInterface $workflowContext): bool;
}
