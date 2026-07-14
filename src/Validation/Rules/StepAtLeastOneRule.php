<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepAtLeastOneRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            if ($w->steps === []) {
                $errors->error($this->code(), "workflow '{$w->workflowId}' must declare at least one step.", "/workflows/{$i}/steps");
            }
        }
    }

    public function code(): string
    {
        return 'step.at_least_one';
    }
}
