<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class WorkflowIdPatternRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            if (preg_match('/^\S+$/', $w->workflowId) !== 1) {
                $errors->error($this->code(), "workflowId '{$w->workflowId}' must not contain spaces.", "/workflows/{$i}/workflowId");
            }
        }
    }

    public function code(): string
    {
        return 'workflow.id_pattern';
    }
}
