<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class WorkflowUniqueIdRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $seen = [];
        foreach ($doc->workflows as $i => $w) {
            if (isset($seen[$w->workflowId])) {
                $errors->error($this->code(), "Duplicate workflowId '{$w->workflowId}'.", "/workflows/{$i}/workflowId");
            }
            $seen[$w->workflowId] = true;
        }
    }

    public function code(): string
    {
        return 'workflow.unique_id';
    }
}
