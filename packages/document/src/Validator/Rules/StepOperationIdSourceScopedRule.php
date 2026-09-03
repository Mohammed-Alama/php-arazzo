<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

final class StepOperationIdSourceScopedRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $openapiSources = array_values(array_filter(
            $doc->sourceDescriptions,
            fn ($s) => $s->type === SourceType::Openapi,
        ));

        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->operationId === null) {
                    continue;
                }
                $isQualified = false;
                foreach ($symbols->sourceDescriptions as $srcName => $source) {
                    if (str_starts_with($s->operationId, $srcName.'.')) {
                        $isQualified = true;
                        break;
                    }
                }

                if (!$isQualified) {
                    if (count($doc->sourceDescriptions) !== 1) {
                        $errors->error(
                            $this->code(),
                            "Step '{$s->stepId}' uses unqualified operationId '{$s->operationId}' but the document does not declare exactly one sourceDescription.",
                            "/workflows/{$i}/steps/{$j}/operationId",
                        );
                    }
                }
            }
        }
    }

    public function code(): string
    {
        return 'step.operationid_source_scoped';
    }
}
