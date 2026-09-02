<?php

declare(strict_types=1);

namespace Alama\Arazzo\Support;

/**
 * Resolves local JSON Pointer references of the form "#/components/inputs/<name>"
 * inside a workflow inputs schema, replacing them with the referenced component
 * schema. Cycles are guarded with a depth budget.
 */
final class InputSchemaResolver
{
    private const MAX_DEPTH = 16;

    /**
     * @param  array<array-key, mixed>|null  $schema
     * @param  array<array-key, mixed>  $componentInputs
     * @return array<array-key, mixed>|null
     */
    public static function resolve(?array $schema, array $componentInputs): ?array
    {
        if ($schema === null || $componentInputs === []) {
            return $schema;
        }

        /** @var array<string, mixed> $resolved */
        $resolved = self::walk($schema, $componentInputs, 0);

        return $resolved;
    }

    /**
     * @param  array<array-key, mixed>  $componentInputs
     */
    private static function walk(mixed $node, array $componentInputs, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            return $node;
        }

        if (is_array($node)) {
            if (array_is_list($node)) {
                return array_map(fn ($item) => self::walk($item, $componentInputs, $depth + 1), $node);
            }

            if (isset($node['$ref']) && is_string($node['$ref'])) {
                $target = self::lookupLocalRef($node['$ref'], $componentInputs);
                if ($target !== null) {
                    return self::walk($target, $componentInputs, $depth + 1);
                }
            }

            $out = [];
            foreach ($node as $key => $value) {
                $out[$key] = self::walk($value, $componentInputs, $depth + 1);
            }

            return $out;
        }

        return $node;
    }

    /**
     * @param  array<array-key, mixed>  $componentInputs
     * @return array<array-key, mixed>|null
     */
    private static function lookupLocalRef(string $reference, array $componentInputs): ?array
    {
        if (preg_match('~^#/components/inputs/(.+)$~', $reference, $m) !== 1) {
            return null;
        }

        $target = $componentInputs[$m[1]] ?? null;

        return is_array($target) && !array_is_list($target) ? $target : null;
    }
}
