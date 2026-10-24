<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\Data\StepSymbols;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\Data\WorkflowSymbols;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Document\Validator\Support\ExpressionSite;
use Alama\Arazzo\Document\Validator\Support\ExpressionWalker;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Enum\ReferenceKind;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ExpressionUnresolvedStepRefRule implements Rule
{
    public function __construct(private readonly ExpressionEngineInterface $engine) {}

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $workflow = $site->workflow;
            if ($workflow === null) {
                continue;
            }

            $ref = $this->engine->expressionReferences($site->expression->raw);
            if ($ref === null || $ref->kind !== ReferenceKind::Step) {
                continue;
            }

            $this->checkReference($site, $workflow, $ref, $errors);
        }
    }

    private function checkReference(
        ExpressionSite $site,
        WorkflowSymbols $workflow,
        ExpressionReference $ref,
        ErrorCollector $errors,
    ): void {
        $targetStepId = $ref->target ?? $site->currentStepId;

        if ($targetStepId === null) {
            $errors->error($this->code(), 'Expression implicitly references current step but is used outside a step context.', $site->pointer);

            return;
        }

        $target = $workflow->stepsById[$targetStepId] ?? null;
        if ($target === null) {
            $errors->error($this->code(), "Expression references unknown step '{$targetStepId}'.", $site->pointer);

            return;
        }

        if ($this->isForwardReference($site, $workflow, $ref, $targetStepId, $target)) {
            $this->reportForwardReference($ref, $workflow, $site->pointer, $errors);

            return;
        }

        if ($ref->part === 'outputs' && $ref->name !== null && !isset($target->outputs[$ref->name])) {
            $errors->error($this->code(), "Step '{$ref->target}' does not declare output '{$ref->name}'.", $site->pointer);
        }
    }

    private function isForwardReference(
        ExpressionSite $site,
        WorkflowSymbols $workflow,
        ExpressionReference $ref,
        string $targetStepId,
        StepSymbols $target,
    ): bool {
        $currentStepId = $site->currentStepId;
        if ($currentStepId === null || !isset($workflow->stepsById[$currentStepId])) {
            return false;
        }

        return $ref->target !== null
            && $targetStepId !== $currentStepId
            && $target->index >= $workflow->stepsById[$currentStepId]->index;
    }

    private function reportForwardReference(
        ExpressionReference $ref,
        WorkflowSymbols $workflow,
        string $pointer,
        ErrorCollector $errors,
    ): void {
        $message = "Expression references step '{$ref->target}' which appears later in the steps array";

        // A reference to an earlier step is a valid implicit dependency
        // (Arazzo 1.1 "Tool Behavior"): the engine must order it before the
        // referencing step even without an explicit dependsOn entry.
        //
        // A forward reference is only statically unsatisfiable when the
        // workflow relies on pure sequential execution (no dependsOn used).
        // Otherwise the engine is expected to infer the implicit edge.
        if ($this->workflowUsesDependsOn($workflow)) {
            $errors->warning('expr.forward_step_ref', "{$message}; it forms an implicit dependency.", $pointer);

            return;
        }

        $errors->error($this->code(), "{$message}, and the workflow does not use dependsOn to make the ordering explicit.", $pointer);
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
