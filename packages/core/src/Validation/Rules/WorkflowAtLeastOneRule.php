<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rule;

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
