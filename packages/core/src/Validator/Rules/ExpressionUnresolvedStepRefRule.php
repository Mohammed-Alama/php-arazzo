<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\Ast\OutputPart;
use Alama\Arazzo\Expression\Ast\StepRef;
use Alama\Arazzo\Expression\ExpressionSyntaxException;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;
use Alama\Arazzo\Validator\Support\ExpressionWalker;

final class ExpressionUnresolvedStepRefRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) {
                continue;
            }
            if (!$ast instanceof StepRef) {
                continue;
            }

            $syms = $site->workflow;
            if ($syms === null) {
                continue;
            }
            $target = $syms->stepsById[$ast->stepId] ?? null;
            if ($target === null) {
                $errors->error($this->code(), "Expression references unknown step '{$ast->stepId}'.", $site->pointer);

                continue;
            }
            if ($site->currentStepId !== null && isset($syms->stepsById[$site->currentStepId])) {
                $currentIdx = $syms->stepsById[$site->currentStepId]->index;
                if ($target->index >= $currentIdx) {
                    $errors->error($this->code(), "Expression references step '{$ast->stepId}' which is not before the current step.", $site->pointer);

                    continue;
                }
            }
            if ($ast->part instanceof OutputPart && !isset($target->outputs[$ast->part->name])) {
                $errors->error($this->code(), "Step '{$ast->stepId}' does not declare output '{$ast->part->name}'.", $site->pointer);
            }
        }
    }

    public function code(): string
    {
        return 'expr.unresolved_step_ref';
    }
}
