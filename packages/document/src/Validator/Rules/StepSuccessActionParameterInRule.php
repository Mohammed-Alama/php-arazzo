<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Action\SuccessEndAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessGotoAction;
use Alama\Arazzo\Contracts\Spec\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class StepSuccessActionParameterInRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        // Check onSuccess parameters on all steps
        foreach ($doc->workflows as $wi => $w) {
            foreach ($w->steps as $si => $s) {
                // $s->flow->onSuccess is list<SuccessGotoAction|SuccessEndAction|SubWorkflowSuccessAction|Reusable>
                $this->checkOnSuccessParameters($s->flow->onSuccess, $errors, "/workflows/{$wi}/steps/{$si}/onSuccess");
                // $s->flow->onFailure is list<SuccessGotoAction|SuccessEndAction|SubWorkflowSuccessAction|Reusable>
                $this->checkOnFailureParameters($s->flow->onFailure, $errors, "/workflows/{$wi}/steps/{$si}/onFailure");
            }
        }

        // Check successActions at workflow level
        foreach ($doc->workflows as $wi => $w) {
            // $w->successActions is list<SuccessGotoAction|SuccessEndAction|SubWorkflowSuccessAction|Reusable>
            foreach ($w->successActions as $ai => $a) {
                $this->checkActionParameters($a, $errors, "/workflows/{$wi}/successActions/{$ai}");
            }
        }

        // Check failureActions at workflow level
        foreach ($doc->workflows as $wi => $w) {
            // $w->failureActions is list<SuccessGotoAction|SuccessEndAction|SubWorkflowSuccessAction|Reusable>
            foreach ($w->failureActions as $ai => $a) {
                $this->checkActionParameters($a, $errors, "/workflows/{$wi}/failureActions/{$ai}");
            }
        }
    }

    /**
     * @param  list<SuccessGotoAction|SuccessEndAction|SubWorkflowSuccessAction>  $actions
     * @psalm-allow MixedArray foreach ($actions as $ai => $action)
     */
    private function checkOnSuccessParameters(array $actions, ErrorCollector $errors, string $base): void
    {
        foreach ($actions as $ai => $action) {
            if ($action instanceof Reusable) {
                continue;
            }

            /** @var SuccessGotoAction|SuccessEndAction|SubWorkflowSuccessAction $action */
            $this->checkActionParameters($action->parameters ?? [], $errors, "{$base}/{$ai}");
        }
    }

    /**
     * @param  list<SuccessGotoAction|SuccessEndAction|SubWorkflowSuccessAction>  $actions
     */
    private function checkOnFailureParameters(array $actions, ErrorCollector $errors, string $base): void
    {
        foreach ($actions as $ai => $action) {
            if ($action instanceof Reusable) {
                continue;
            }
            // Only check success action parameters, not failure action parameters
            // (per spec, only success action parameters must not use 'in')
        }
    }

    /**
     * @param  list<SuccessGotoAction|SuccessEndAction|SubWorkflowSuccessAction>  $parameters
     */
    private function checkActionParameters(array $parameters, ErrorCollector $errors, string $base): void
    {
        foreach ($parameters as $pi => $p) {
            if ($p instanceof Reusable) {
                continue;
            }

            if ($p->in !== null) {
                $errors->error(
                    $this->code(),
                    "Parameter '{$p->name}' with 'in' value '{$p->in->value}' MUST NOT be used on success action parameters.",
                    "{$base}/{$pi}/in",
                );
            }
        }
    }

    public function code(): string
    {
        return 'step.success_action_parameter_in';
    }
}