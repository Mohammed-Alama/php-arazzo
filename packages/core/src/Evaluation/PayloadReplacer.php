<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Contracts\WorkflowContext;
use Alama\Arazzo\Expression\JsonPathEvaluator;
use Alama\Arazzo\Expression\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Spec\PayloadReplacement;
use Alama\Arazzo\Spec\Step;

/**
 * Applies a step's payload replacements to an array-shaped body.
 *
 * Two target forms (Arazzo 1.1 Payload Replacement Object):
 * - JSON Pointer targets ("/a/b") write into the body directly.
 * - Selector targets declared via targetSelectorType (jsonpath/xpath) locate
 *   node(s) in the current body which are then overwritten with the value.
 *
 * Shared by every protocol executor so replacement semantics stay identical
 * across HTTP, AsyncAPI and sub-workflow steps.
 */
final class PayloadReplacer
{
    /**
     * @param  array<array-key, mixed>  $body
     * @param  callable(PayloadReplacement): mixed|null  $resolveValue  invoked for each replacement (Expression evaluation etc.)
     * @return array<array-key, mixed>
     */
    public static function apply(Step $step, array $body, ?callable $resolveValue = null, ?WorkflowContext $context = null): array
    {
        $requestBody = $step->requestBody;
        if ($requestBody === null || $requestBody->replacements === []) {
            return $body;
        }

        foreach ($requestBody->replacements as $replacement) {
            if (str_starts_with($replacement->target, '/')) {
                $value = $resolveValue !== null ? $resolveValue($replacement) : $replacement->value;
                self::setAtPointer($body, $replacement->target, $value);

                continue;
            }

            if ($context !== null && self::isSelectorTarget($replacement)) {
                self::applySelectorTarget($body, $replacement, $context, $resolveValue);
            }
        }

        return $body;
    }

    private static function isSelectorTarget(PayloadReplacement $replacement): bool
    {
        $type = self::selectorType($replacement);

        return $type === 'jsonpath' || $type === 'xpath';
    }

    /**
     * @return 'jsonpointer'|'jsonpath'|'xpath'|null
     */
    private static function selectorType(PayloadReplacement $replacement): ?string
    {
        if (is_array($replacement->targetSelectorType)) {
            $type = $replacement->targetSelectorType['type'] ?? null;

            return is_string($type) && in_array($type, ['jsonpointer', 'jsonpath', 'xpath'], true) ? $type : null;
        }

        return is_string($replacement->targetSelectorType) ? $replacement->targetSelectorType : null;
    }

    /**
     * Overwrites scalar leaves matched by the selector expression with the
     * replacement value. Best-effort mapping of JSONPath dot notation onto
     * nested keys; unmatched expressions are ignored silently.
     *
     * @param  array<array-key, mixed>  $body
     */
    private static function applySelectorTarget(
        array &$body,
        PayloadReplacement $replacement,
        WorkflowContext $context,
        ?callable $resolveValue,
    ): void {
        $value = $resolveValue !== null ? $resolveValue($replacement) : $replacement->value;
        $type = (string) self::selectorType($replacement);

        $evaluator = new DomXpathEvaluator();
        $found = match ($type) {
            'xpath' => $evaluator->query($body, $replacement->target, 'xpath-10'),
            default => JsonPathEvaluator::evaluate($replacement->target, $body),
        };

        if ($found === null || is_array($found)) {
            return;
        }

        $segments = preg_split('/[.\']/', trim($replacement->target, '$.')) ?: [];
        $current = &$body;
        foreach ($segments as $segment) {
            $segment = str_replace(['@.', '[', ']', '"'], '', $segment);
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                return;
            }
            $current = &$current[$segment];
        }

        $current = $value;
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    private static function setAtPointer(array &$body, string $target, mixed $value): void
    {
        $segments = explode('/', ltrim($target, '/'));
        $current = &$body;

        foreach ($segments as $i => $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

            if ($i === count($segments) - 1) {
                $current[$segment] = $value;

                return;
            }

            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }
    }
}
