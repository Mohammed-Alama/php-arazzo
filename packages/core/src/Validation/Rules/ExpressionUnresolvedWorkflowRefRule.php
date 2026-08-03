<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Expression\Ast\WorkflowRef;
use Alama\Arazzo\Expression\ExpressionSyntaxException;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rule;
use Alama\Arazzo\Validation\Support\ExpressionWalker;

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
