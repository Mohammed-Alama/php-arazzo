<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\Action\FailureGotoAction;
use Alama\Arazzo\Contracts\Spec\Action\RetryAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessGotoAction;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\Data\WorkflowSymbols;
use Alama\Arazzo\Expression\SymbolTable;

final class ActionGotoTargetResolvesRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wi => $w) {
            $syms = $symbols->workflows[$w->workflowId] ?? null;
            foreach ($w->steps as $si => $s) {
                $this->checkList($s->onSuccess, $syms, $symbols, $errors, "/workflows/{$wi}/steps/{$si}/onSuccess");
                $this->checkList($s->onFailure, $syms, $symbols, $errors, "/workflows/{$wi}/steps/{$si}/onFailure");
            }
        }
    }

    /** @param list<mixed> $actions */
    private function checkList(array $actions, ?WorkflowSymbols $syms, SymbolTable $global, ErrorCollector $errors, string $base): void
    {
        foreach ($actions as $i => $a) {
            $stepId = null;
            $workflowId = null;
            if ($a instanceof SuccessGotoAction || $a instanceof FailureGotoAction || $a instanceof RetryAction) {
                $stepId = $a->stepId;
                $workflowId = $a->workflowId;
            } else {
                continue;
            }
            if ($stepId !== null && ($syms === null || !isset($syms->stepsById[$stepId]))) {
                $errors->error($this->code(), "Action references unknown stepId '{$stepId}'.", "{$base}/{$i}/stepId");
            }
            if ($workflowId !== null && !isset($global->workflows[$workflowId])) {
                $errors->error($this->code(), "Action references unknown workflowId '{$workflowId}'.", "{$base}/{$i}/workflowId");
            }
        }
    }

    public function code(): string
    {
        return 'action.goto_target_resolves';
    }
}
