<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Interfaces\Rule;

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
