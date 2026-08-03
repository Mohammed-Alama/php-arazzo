<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Enum\ParameterIn;
use Alama\Arazzo\Dto\Enum\SpecVersion;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepParameterInValidRule implements Rule
{
    public function code(): string
    {
        return 'step.parameter_in_valid';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wi => $w) {
            foreach ($w->steps as $si => $s) {
                foreach ($s->parameters as $pi => $p) {
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
}
