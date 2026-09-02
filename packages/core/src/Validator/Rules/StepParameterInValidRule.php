<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\ParameterIn;
use Alama\Arazzo\Spec\Enum\SpecVersion;
use Alama\Arazzo\Spec\Reusable;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Interfaces\Rule;

final class StepParameterInValidRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wi => $w) {
            foreach ($w->steps as $si => $s) {
                foreach ($s->parameters as $pi => $p) {
                    if ($p instanceof Reusable) {
                        continue;
                    }

                    if ($p->in === ParameterIn::Querystring && $doc->specVersion === SpecVersion::V1_0) {
                        $errors->error(
                            $this->code(),
                            "Parameter 'in' value 'querystring' requires Arazzo 1.1.0.",
                            "/workflows/{$wi}/steps/{$si}/parameters/{$pi}/in",
                        );
                    }
                }
            }
        }

        foreach ($doc->components->parameters as $name => $p) {
            if ($p->in === ParameterIn::Querystring && $doc->specVersion === SpecVersion::V1_0) {
                $errors->error(
                    $this->code(),
                    "Parameter 'in' value 'querystring' requires Arazzo 1.1.0.",
                    "/components/parameters/{$name}/in",
                );
            }
        }
    }

    public function code(): string
    {
        return 'step.parameter_in_valid';
    }
}
