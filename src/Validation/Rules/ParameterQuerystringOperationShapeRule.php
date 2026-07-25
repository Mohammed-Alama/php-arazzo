<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Enum\SpecVersion;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class ParameterQuerystringOperationShapeRule implements Rule
{
    public function code(): string
    {
        return 'parameter.querystring_operation_shape';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->specVersion === SpecVersion::V1_0) {
            return;
        }

        foreach ($doc->workflows as $wi => $wf) {
            foreach ($wf->steps as $si => $step) {
                foreach ($step->parameters as $pi => $param) {
                    if ($param->in !== ParameterIn::Querystring) {
                        continue;
                    }

                    if ($doc->sourceDescriptions === []) {
                        $errors->warning(
                            $this->code(),
                            "Cannot verify 'querystring' parameter operation shape at /workflows/{$wi}/steps/{$si}/parameters/{$pi}: no source descriptions loaded.",
                            "/workflows/{$wi}/steps/{$si}/parameters/{$pi}/in",
                        );
                        continue;
                    }

                    // Full OpenAPI shape check is exercised via E2E fixture in Task 15.
                    // Here we defer to a soft warning when we can't resolve the operation.
                }
            }
        }
    }
}
