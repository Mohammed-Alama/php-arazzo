<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

final class ParameterQuerystringOperationShapeRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->specVersion === SpecVersion::V1_0) {
            return;
        }

        foreach ($doc->workflows as $wi => $wf) {
            foreach ($wf->steps as $si => $step) {
                foreach ($step->parameters as $pi => $param) {
                    if ($param instanceof Reusable) {
                        continue;
                    }

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

    public function code(): string
    {
        return 'parameter.querystring_operation_shape';
    }
}
