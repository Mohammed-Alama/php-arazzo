<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\WorkflowRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionUnresolvedWorkflowRefRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) {
                continue;
            }
            if (!$ast instanceof WorkflowRef) {
                continue;
            }

            $target = $symbols->workflows[$ast->workflowId] ?? null;
            if ($target === null) {
                $errors->error($this->code(), "Expression references unknown workflow '{$ast->workflowId}'.", $site->pointer);

                continue;
            }
            if ($site->workflow !== null && !isset($site->workflow->dependsOn[$ast->workflowId])) {
                $errors->error($this->code(), "Expression references workflow '{$ast->workflowId}' which is not in dependsOn.", $site->pointer);

                continue;
            }
            $bag = $ast->partKind === 'inputs' ? $target->inputs : $target->outputs;
            if (!isset($bag[$ast->name])) {
                $errors->error($this->code(), "Workflow '{$ast->workflowId}' has no {$ast->partKind}.{$ast->name}.", $site->pointer);
            }
        }
    }

    public function code(): string
    {
        return 'expr.unresolved_workflow_ref';
    }
}
