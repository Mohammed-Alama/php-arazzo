<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * wsdl-step rules (PR #533):
 *  - If a step sets operationName, the corresponding source must be of type wsdl.
 *  - A wsdl step must not also set operationPath (or operationId? but rule says operationPath).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class WsdlStepRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        // Build a map of source name -> source type
        $sourceTypes = [];
        foreach ($doc->sourceDescriptions as $src) {
            $sourceTypes[$src->name] = $src->type;
        }

        foreach ($doc->workflows as $wfIndex => $wf) {
            foreach ($wf->steps as $stepIndex => $step) {
                $target = $step->target;

                // If operationName is set, it's a wsdl step
                if ($target->operationName !== null) {
                    // Determine the source name referenced by this step
                    $sourceName = $this->resolveSourceName($target, $doc);
                    if ($sourceName === null) {
                        // No source referenced; cannot validate source type
                        continue;
                    }
                    $sourceType = $sourceTypes[$sourceName] ?? null;
                    if ($sourceType !== SourceType::Wsdl) {
                        $typeValue = $sourceType instanceof SourceType ? $sourceType->value : 'unknown';
                        $errors->error(
                            $this->code(),
                            "Step '{$step->stepId}' uses operationName (wsdl) but references source '{$sourceName}' of type {$typeValue}. wsdl steps require a wsdl source.",
                            "/workflows/{$wfIndex}/steps/{$stepIndex}",
                        );
                    }

                    // Disallow operationPath on wsdl steps
                    if ($target->operationPath !== null) {
                        $errors->error(
                            $this->code(),
                            "Step '{$step->stepId}' uses operationName (wsdl) and also sets operationPath; wsdl steps must not set operationPath.",
                            "/workflows/{$wfIndex}/steps/{$stepIndex}",
                        );
                    }
                }
            }
        }
    }

    private function resolveSourceName(StepTarget $target, ArazzoDocument $doc): ?string
    {
        // If operationId is a qualified reference $sourceDescriptions.<name>.<opId>, extract name
        if ($target->operationId !== null && str_starts_with($target->operationId, '$sourceDescriptions.')) {
            $parts = explode('.', $target->operationId, 3);
            if (count($parts) >= 2) {
                return $parts[1];
            }
        }
        // If operationPath is a JSON pointer to a path under a source? Not typical.
        // Fallback: if document has only one non-arazzo source, assume that.
        $nonArazzo = array_filter($doc->sourceDescriptions, fn ($s) => $s->type !== SourceType::Arazzo);
        if (count($nonArazzo) === 1) {
            $first = reset($nonArazzo);

            return $first->name;
        }

        return null;
    }

    public function code(): string
    {
        return 'step.wsdl_step';
    }
}
