<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

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
