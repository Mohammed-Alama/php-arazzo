<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * wsdl-step rules (Arazzo Specification, Step Object):
 *  - A step that references a `wsdl` source MUST use `operationName`, matching
 *    a `wsdl:operation` name. `operationId` and `operationPath` MUST NOT be
 *    used to reference a `wsdl` source.
 *  - When a step sets `operationName`, the resolved source MUST be of type
 *    `wsdl`, and the step MUST NOT also set `operationPath`.
 *
 * The JSON Schema cannot resolve the source type at validation time, so this
 * prose rule is enforced here (see the specification's characterization note).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class WsdlStepRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wfIndex => $wf) {
            foreach ($wf->steps as $stepIndex => $step) {
                $this->checkStep($step, $wfIndex, $stepIndex, $doc, $errors);
            }
        }
    }

    private function checkStep(Step $step, int $wfIndex, int $stepIndex, ArazzoDocument $doc, ErrorCollector $errors): void
    {
        $target = $step->target;
        if (!self::isSourceBound($target)) {
            return;
        }

        $source = $this->resolveSource($target, $doc);
        $pointer = "/workflows/{$wfIndex}/steps/{$stepIndex}";

        if ($target->operationName !== null) {
            $this->checkOperationName($step, $target, $source, $pointer, $errors);

            return;
        }

        $this->checkNonNameReference($step, $target, $source, $pointer, $errors);
    }

    private function checkOperationName(
        Step $step,
        StepTarget $target,
        ?SourceDescription $source,
        string $pointer,
        ErrorCollector $errors,
    ): void {
        if ($source !== null && $source->type !== SourceType::Wsdl) {
            $errors->error(
                $this->code(),
                "Step '{$step->stepId}' uses operationName (wsdl) but references source '{$source->name}' of type {$source->type->value}. wsdl steps require a wsdl source.",
                $pointer,
            );
        }

        if ($target->operationPath !== null) {
            $errors->error(
                $this->code(),
                "Step '{$step->stepId}' uses operationName (wsdl) and also sets operationPath; wsdl steps must not set operationPath.",
                $pointer,
            );
        }
    }

    private function checkNonNameReference(
        Step $step,
        StepTarget $target,
        ?SourceDescription $source,
        string $pointer,
        ErrorCollector $errors,
    ): void {
        // operationId / operationPath MUST NOT reference a wsdl source.
        if ($source === null || $source->type !== SourceType::Wsdl) {
            return;
        }

        if ($target->operationId === null && $target->operationPath === null) {
            return;
        }

        $field = $target->operationId !== null ? 'operationId' : 'operationPath';
        $errors->error(
            $this->code(),
            "Step '{$step->stepId}' references source '{$source->name}' of type wsdl via {$field}; a wsdl source must be referenced with operationName, not {$field}.",
            $pointer,
        );
    }

    private static function isSourceBound(StepTarget $target): bool
    {
        return $target->operationName !== null
            || $target->operationId !== null
            || $target->operationPath !== null;
    }

    private function resolveSource(StepTarget $target, ArazzoDocument $doc): ?SourceDescription
    {
        $name = $this->qualifiedSourceName($target) ?? $this->operationPathSourceName($target);
        if ($name !== null) {
            return self::findSource($doc, $name);
        }

        // Fallback: a single non-arazzo source leaves no ambiguity.
        $nonArazzo = array_values(array_filter(
            $doc->sourceDescriptions,
            static fn (SourceDescription $s): bool => $s->type !== SourceType::Arazzo,
        ));

        return count($nonArazzo) === 1 ? $nonArazzo[0] : null;
    }

    private function qualifiedSourceName(StepTarget $target): ?string
    {
        // Qualified runtime-expression form: $sourceDescriptions.<name>.<ref>
        // (used by operationName and operationId).
        foreach ([$target->operationName, $target->operationId] as $ref) {
            if ($ref !== null && str_starts_with($ref, '$sourceDescriptions.')) {
                $parts = explode('.', $ref, 3);
                if (isset($parts[1]) && $parts[1] !== '') {
                    return $parts[1];
                }
            }
        }

        return null;
    }

    private function operationPathSourceName(StepTarget $target): ?string
    {
        if ($target->operationPath === null) {
            return null;
        }

        return preg_match('/\{\$sourceDescriptions\.([^}.]+)\.url\}/', $target->operationPath, $m) === 1 ? $m[1] : null;
    }

    private static function findSource(ArazzoDocument $doc, string $name): ?SourceDescription
    {
        foreach ($doc->sourceDescriptions as $candidate) {
            if ($candidate->name === $name) {
                return $candidate;
            }
        }

        return null;
    }

    public function code(): string
    {
        return 'step.wsdl_step';
    }
}
