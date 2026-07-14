<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

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
                if (str_contains($s->operationId, '#')) {
                    [$src] = explode('#', $s->operationId, 2);
                    if (!isset($symbols->sourceDescriptions[$src])) {
                        $errors->error(
                            $this->code(),
                            "Step '{$s->stepId}' operationId references unknown source '{$src}'.",
                            "/workflows/{$i}/steps/{$j}/operationId",
                        );
                    }
                } else {
                    if (count($openapiSources) !== 1) {
                        $errors->error(
                            $this->code(),
                            "Step '{$s->stepId}' uses unqualified operationId '{$s->operationId}' but the document does not declare exactly one openapi sourceDescription.",
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
