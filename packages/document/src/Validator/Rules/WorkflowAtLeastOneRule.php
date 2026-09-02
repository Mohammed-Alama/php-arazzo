<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Interfaces\Rule;

final class WorkflowAtLeastOneRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->workflows === []) {
            $errors->error($this->code(), 'Document must declare at least one workflow.', '/workflows');
        }
    }

    public function code(): string
    {
        return 'workflow.at_least_one';
    }
}
