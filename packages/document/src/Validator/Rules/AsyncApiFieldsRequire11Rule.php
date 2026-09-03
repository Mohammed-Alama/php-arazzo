<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

final class AsyncApiFieldsRequire11Rule implements Rule
{
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

    public function code(): string
    {
        return 'asyncapi.fields_require_11';
    }
}
