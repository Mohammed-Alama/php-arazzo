<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Selector;
use Alama\Arazzo\Spec\Step;

/**
 * Extracts implicit dependencies from runtime expression output references
 * (e.g. {$steps.other.outputs.id}) per the Arazzo 1.1 "Tool Behavior" section:
 * such references create ordering edges exactly like an explicit dependsOn.
 */
final class ImplicitDependencies
{
    private const OUTPUT_REF_PATTERN = '/\$steps\.([^.\s}\$]+)\.outputs\b/';

    /**
     * @return list<string> referenced stepIds (deduplicated, self excluded)
     */
    public static function fromStep(Step $step): array
    {
        $fragments = [];

        foreach ($step->parameters as $parameter) {
            $fragments[] = $parameter->value;
        }

        if ($step->requestBody !== null) {
            $fragments[] = $step->requestBody->payload;
            foreach ($step->requestBody->replacements as $replacement) {
                $fragments[] = $replacement->value;
            }
        }

        foreach ($step->successCriteria as $criterion) {
            $fragments[] = $criterion->context;
            $fragments[] = $criterion->condition;
        }

        if ($step->correlationId !== null) {
            $fragments[] = $step->correlationId;
        }

        foreach ($step->outputs as $expression) {
            if ($expression instanceof Expression || $expression instanceof Selector) {
                $fragments[] = $expression;
            }
        }

        $ids = [];
        foreach ($fragments as $fragment) {
            foreach (self::extractStepRefs($fragment) as $ref) {
                if ($ref !== $step->stepId) {
                    $ids[$ref] = true;
                }
            }
        }

        /** @var list<string> */
        return array_keys($ids);
    }

    /**
     * @return list<string>
     */
    private static function extractStepRefs(mixed $node): array
    {
        if ($node instanceof Expression) {
            return self::matchRefs($node->raw);
        }

        if ($node instanceof Selector) {
            return self::matchRefs($node->context ?? '');
        }

        if (is_string($node)) {
            return self::matchRefs($node);
        }

        if (is_array($node)) {
            $refs = [];
            foreach ($node as $value) {
                foreach (self::extractStepRefs($value) as $ref) {
                    $refs[] = $ref;
                }
            }

            return $refs;
        }

        return [];
    }

    /** @return list<string> */
    private static function matchRefs(string $text): array
    {
        if ($text === '' || !str_contains($text, '$steps.')) {
            return [];
        }

        if (preg_match_all(self::OUTPUT_REF_PATTERN, $text, $m) === 0) {
            return [];
        }

        return $m[1];
    }
}
