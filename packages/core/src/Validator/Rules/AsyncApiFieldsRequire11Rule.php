<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Enum\SpecVersion;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;

final class AsyncApiFieldsRequire11Rule implements Rule
{
    public function code(): string
    {
        return 'asyncapi.fields_require_11';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->specVersion !== SpecVersion::V1_0) {
            return;
        }

        foreach ($doc->workflows as $wi => $wf) {
            foreach ($wf->steps as $si => $step) {
                foreach (['action', 'channelPath', 'correlationId'] as $field) {
                    if ($step->{$field} !== null) {
                        $errors->error(
                            $this->code(),
                            "Field '{$field}' requires arazzo 1.1.0+ at /workflows/{$wi}/steps/{$si}/{$field}",
                            "/workflows/{$wi}/steps/{$si}/{$field}",
                        );
                    }
                }
            }
        }
    }
}
