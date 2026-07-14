<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\Action\FailureGotoAction;
use Alama\LaravelArazzo\Dto\Action\RetryAction;
use Alama\LaravelArazzo\Dto\Action\SuccessGotoAction;
use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Expression\WorkflowSymbols;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

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
