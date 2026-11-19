<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * Resolves an operationId against the operations actually declared in its
 * referenced source description.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class StepOperationIdResolvesRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $singleNonArazoSource = $this->singleNonArazoSource($doc);

        foreach ($doc->workflows as $wi => $w) {
            foreach ($w->steps as $si => $s) {
                $operationId = $s->target->operationId;
                if ($operationId === null) {
                    continue;
                }

                [$sourceName, $operation] = $this->parse($operationId, $singleNonArazoSource);

                if ($sourceName === null) {
                    // Cannot determine a source; qualification gate is handled
                    // by StepOperationIdSourceScopedRule.
                    continue;
                }

                if (!isset($symbols->sourceDescriptions[$sourceName])) {
                    $errors->error(
                        $this->code(),
                        "Step '{$s->stepId}' uses unknown source qualifier '{$sourceName}' in operationId '{$operationId}'.",
                        "/workflows/{$wi}/steps/{$si}/operationId",
                    );

                    continue;
                }

                $resolved = $symbols->resolvedOperations[$sourceName] ?? null;
                if (!is_array($resolved) || $resolved === []) {
                    // No resolved operations available (unreachable/skipped
                    // source): skip the existence check (R5).
                    continue;
                }

                if (!isset($resolved[$operation])) {
                    $errors->error(
                        $this->code(),
                        "Step '{$s->stepId}' uses unresolved operationId '{$operationId}'.",
                        "/workflows/{$wi}/steps/{$si}/operationId",
                    );
                }
            }
        }
    }

    /**
     * @return array{0:?string,1:string}
     */
    private function parse(string $operationId, ?string $singleNonArazoSource): array
    {
        $candidate = $operationId;
        if (str_starts_with($candidate, '$sourceDescriptions.')) {
            $candidate = substr($candidate, strlen('$sourceDescriptions.'));
        }

        if (str_contains($candidate, '.')) {
            $parts = explode('.', $candidate, 2);

            return [$parts[0], $parts[1]];
        }

        // Unqualified: only meaningful when a single non-arazo source exists.
        return [$singleNonArazoSource, $operationId];
    }

    private function singleNonArazoSource(ArazzoDocument $doc): ?string
    {
        $names = [];
        foreach ($doc->sourceDescriptions as $source) {
            if ($source->type !== SourceType::Arazzo) {
                $names[] = $source->name;
            }
        }

        return count($names) === 1 ? $names[0] : null;
    }

    public function code(): string
    {
        return 'step.operationid_resolves';
    }
}
