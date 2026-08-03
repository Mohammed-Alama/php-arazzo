<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepParametersHaveNameRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                foreach ($s->parameters as $k => $p) {
                    if (trim($p->name) === '') {
                        $errors->error(
                            $this->code(),
                            "Parameter at index {$k} of step '{$s->stepId}' must have a non-empty name.",
                            "/workflows/{$i}/steps/{$j}/parameters/{$k}/name",
                        );
                    }
                }
            }
        }
    }

    public function code(): string
    {
        return 'step.parameters_have_name';
    }
}
