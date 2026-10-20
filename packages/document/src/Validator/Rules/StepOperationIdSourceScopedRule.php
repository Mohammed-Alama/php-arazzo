<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class StepOperationIdSourceScopedRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->target->operationId === null) {
                    continue;
                }
                $isQualified = false;
                foreach (array_keys($symbols->sourceDescriptions) as $srcName) {
                    // Accept both the spec runtime-expression form
                    // `$sourceDescriptions.<name>.<operationId>` and the
                    // bare `<name>.<operationId>` form.
                    if (
                        str_starts_with($s->target->operationId, $srcName.'.')
                        || str_starts_with($s->target->operationId, '$sourceDescriptions.'.$srcName.'.')
                    ) {
                        $isQualified = true;
                        break;
                    }
                }

                if (!$isQualified) {
                    if (count($doc->sourceDescriptions) !== 1) {
                        $errors->error(
                            $this->code(),
                            "Step '{$s->stepId}' uses unqualified operationId '{$s->target->operationId}' but the document does not declare exactly one sourceDescription.",
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
