<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepNestedWorkflowExistsRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->workflowId === null) {
                    continue;
                }
                if (!isset($symbols->workflows[$s->workflowId])) {
                    $errors->error(
                        $this->code(),
                        "step.workflowId '{$s->workflowId}' does not resolve to a declared workflow.",
                        "/workflows/{$i}/steps/{$j}/workflowId",
                    );
                }
            }
        }
    }

    public function code(): string
    {
        return 'step.nested_workflow_exists';
    }
}
