<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\Ast\OutputPart;
use Alama\Arazzo\Expression\Ast\StepRef;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Parser as ExpressionParser;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Expression\WorkflowSymbols;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;
use Alama\Arazzo\Validator\Support\ExpressionWalker;

final class ExpressionUnresolvedStepRefRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = (new ExpressionParser())->parseOrError($site->expression->raw);
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

            $targetStepId = $ast->stepId ?? $site->currentStepId;

            if ($targetStepId === null) {
                $errors->error($this->code(), 'Expression implicitly references current step but is used outside a step context.', $site->pointer);

                continue;
            }

            $target = $syms->stepsById[$targetStepId] ?? null;
            if ($target === null) {
                $errors->error($this->code(), "Expression references unknown step '{$targetStepId}'.", $site->pointer);

                continue;
            }

            if ($site->currentStepId !== null && isset($syms->stepsById[$site->currentStepId])) {
                $currentIdx = $syms->stepsById[$site->currentStepId]->index;

                if ($ast->stepId !== null && $targetStepId !== $site->currentStepId && $target->index >= $currentIdx) {
                    // A reference to an earlier step is a valid implicit dependency
                    // (Arazzo 1.1 "Tool Behavior"): the engine must order it before
                    // the referencing step even without an explicit dependsOn entry.

                    // A forward reference is only statically unsatisfiable when the
                    // workflow relies on pure sequential execution (no dependsOn used).
                    // Otherwise the engine is expected to infer the implicit edge.
                    if ($this->workflowUsesDependsOn($syms)) {
                        $errors->warning('expr.forward_step_ref', "Expression references step '{$ast->stepId}' which appears later in the steps array; it forms an implicit dependency.", $site->pointer);
                    } else {
                        $errors->error($this->code(), "Expression references step '{$ast->stepId}' which appears later in the steps array, and the workflow does not use dependsOn to make the ordering explicit.", $site->pointer);
                    }

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

    private function workflowUsesDependsOn(WorkflowSymbols $syms): bool
    {
        foreach ($syms->stepsById as $step) {
            if ($step->dependsOn !== []) {
                return true;
            }
        }

        return false;
    }
}
