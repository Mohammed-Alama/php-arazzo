<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
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
        // Only non-arazo sources count toward the qualification gate: an arazzo
        // source describes workflows, not operationIds, so it does not force
        // qualification. Compute once; the gate is document-wide, not per step.
        $sourceNames = array_keys($symbols->sourceDescriptions);
        $requireQualification = $this->countNonArazoSources($doc->sourceDescriptions) !== 1;

        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->target->operationId === null) {
                    continue;
                }
                if (!$requireQualification) {
                    continue;
                }
                if (!$this->isQualifiedOperationId($s->target->operationId, $sourceNames)) {
                    $errors->error(
                        $this->code(),
                        "Step '{$s->stepId}' uses unqualified operationId '{$s->target->operationId}' but the document does not declare exactly one non-arazo source.",
                        "/workflows/{$i}/steps/{$j}/operationId",
                    );
                }
            }
        }
    }

    /**
     * Accept both the spec runtime-expression form
     * `$sourceDescriptions.<name>.<operationId>` and the bare `<name>.<operationId>` form.
     *
     * @param  list<string>  $sourceNames
     */
    private function isQualifiedOperationId(string $operationId, array $sourceNames): bool
    {
        foreach ($sourceNames as $srcName) {
            if (
                str_starts_with($operationId, $srcName.'.')
                || str_starts_with($operationId, '$sourceDescriptions.'.$srcName.'.')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<SourceDescription>  $sourceDescriptions
     */
    private function countNonArazoSources(array $sourceDescriptions): int
    {
        $count = 0;
        foreach ($sourceDescriptions as $source) {
            if ($source->type !== SourceType::Arazzo) {
                $count++;
            }
        }

        return $count;
    }

    public function code(): string
    {
        return 'step.operationid_source_scoped';
    }
}
