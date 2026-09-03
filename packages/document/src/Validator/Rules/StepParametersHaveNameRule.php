<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

final class StepParametersHaveNameRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                foreach ($s->parameters as $k => $p) {
                    if ($p instanceof Reusable) {
                        continue;
                    }

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
