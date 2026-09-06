<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Document\Validator\Support\ExpressionWalker;
use Alama\Arazzo\Expression\Data\WorkflowSymbols;
use Alama\Arazzo\Expression\Enum\ReferenceKind;
use Alama\Arazzo\Expression\ExpressionEngineInterface;
use Alama\Arazzo\Expression\SymbolTable;

final class ExpressionUnresolvedStepRefRule implements Rule
{
    public function __construct(private readonly ExpressionEngineInterface $engine) {}

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ref = $this->engine->expressionReferences($site->expression->raw);
            if ($ref === null || $ref->kind !== ReferenceKind::Step) {
                continue;
            }

            $syms = $site->workflow;
            if ($syms === null) {
                continue;
            }

            $targetStepId = $ref->target ?? $site->currentStepId;

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

                if ($ref->target !== null && $targetStepId !== $site->currentStepId && $target->index >= $currentIdx) {
                    // A reference to an earlier step is a valid implicit dependency
                    // (Arazzo 1.1 "Tool Behavior"): the engine must order it before
                    // the referencing step even without an explicit dependsOn entry.

                    // A forward reference is only statically unsatisfiable when the
                    // workflow relies on pure sequential execution (no dependsOn used).
                    // Otherwise the engine is expected to infer the implicit edge.
                    if ($this->workflowUsesDependsOn($syms)) {
                        $errors->warning('expr.forward_step_ref', "Expression references step '{$ref->target}' which appears later in the steps array; it forms an implicit dependency.", $site->pointer);
                    } else {
                        $errors->error($this->code(), "Expression references step '{$ref->target}' which appears later in the steps array, and the workflow does not use dependsOn to make the ordering explicit.", $site->pointer);
                    }

                    continue;
                }
            }
            if ($ref->part === 'outputs' && $ref->name !== null && !isset($target->outputs[$ref->name])) {
                $errors->error($this->code(), "Step '{$ref->target}' does not declare output '{$ref->name}'.", $site->pointer);
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
