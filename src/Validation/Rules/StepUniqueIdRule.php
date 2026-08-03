<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepUniqueIdRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            $seen = [];
            foreach ($w->steps as $j => $s) {
                if (isset($seen[$s->stepId])) {
                    $errors->error(
                        $this->code(),
                        "Duplicate stepId '{$s->stepId}' in workflow '{$w->workflowId}'.",
                        "/workflows/{$i}/steps/{$j}/stepId",
                    );
                }
                $seen[$s->stepId] = true;
            }
        }
    }

    public function code(): string
    {
        return 'step.unique_id';
    }
}
