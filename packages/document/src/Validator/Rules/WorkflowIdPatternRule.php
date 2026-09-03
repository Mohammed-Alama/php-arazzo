<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

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
