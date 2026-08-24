<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation;

use Alama\Arazzo\Spec\PayloadReplacement;
use Alama\Arazzo\Spec\Step;

/**
 * Applies a step's payload replacements (JSON Pointer targets into the
 * request body) to an array-shaped body. Shared by every protocol executor
 * so replacement semantics stay identical across HTTP, AsyncAPI and
 * sub-workflow steps.
 */
final class PayloadReplacer
{
    /**
     * @param array<array-key, mixed> $body
     * @param callable(PayloadReplacement): mixed|null $resolveValue invoked for each replacement (Expression evaluation etc.)
     *
     * @return array<array-key, mixed>
     */
    public static function apply(Step $step, array $body, ?callable $resolveValue = null): array
    {
        $requestBody = $step->requestBody;
        if ($requestBody === null || $requestBody->replacements === []) {
            return $body;
        }

        foreach ($requestBody->replacements as $replacement) {
            // Only pointer-style targets (leading '/') write into the body;
            // other targets are runtime expressions resolved upstream.
            if (!str_starts_with($replacement->target, '/')) {
                continue;
            }

            $value = $resolveValue !== null ? $resolveValue($replacement) : $replacement->value;

            self::setAtPointer($body, $replacement->target, $value);
        }

        return $body;
    }

    /**
     * @param array<array-key, mixed> $body
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
