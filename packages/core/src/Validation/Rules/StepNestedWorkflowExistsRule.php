<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rule;

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
