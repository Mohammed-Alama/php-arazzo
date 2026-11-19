<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Schema;

/**
 * Rewrites draft 2020-12 `$ref` siblings for justinrainbow, which treats a
 * `$ref` as exclusive (draft-7 style) and silently skips sibling keywords such
 * as `required`.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RefHoister
{
    /**
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    public static function hoist(array $node): array
    {
        // $anchor-based local refs (#workflowId/#stepId) resolve to plain strings.
        if (($node['$ref'] ?? null) === '#workflowId' || ($node['$ref'] ?? null) === '#stepId') {
            return ['type' => 'string'];
        }

        if (isset($node['$ref']) && count($node) > 1) {
            return self::hoistSiblingRef($node);
        }

        foreach ($node as $k => $v) {
            if (is_array($v)) {
                $node[$k] = self::hoist($v);
            }
        }

        return $node;
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    private static function hoistSiblingRef(array $node): array
    {
        $ref = ['$ref' => $node['$ref']];
        unset($node['$ref']);

        // A node may already carry an allOf (for example
        // criterion-expression-type-object). Merge the hoisted $ref into that
        // list instead of overwriting it, and recurse into the existing
        // branches so their sibling refs are hoisted too.
        $existing = isset($node['allOf']) && is_array($node['allOf']) ? $node['allOf'] : [];
        unset($node['allOf']);
        $existing = array_map(
            static fn ($entry) => is_array($entry) ? self::hoist($entry) : $entry,
            $existing,
        );

        $hoisted = ['allOf' => array_merge([$ref], $existing)];
        foreach ($node as $k => $v) {
            if (is_array($v)) {
                $v = self::hoist($v);
            }
            $hoisted[$k] = $v;
        }

        return $hoisted;
    }
}
