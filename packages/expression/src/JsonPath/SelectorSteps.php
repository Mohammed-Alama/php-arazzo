<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\JsonPath;

use Alama\Arazzo\Expression\Data\JsonPathBudget;

/**
 * Individual selector step implementations (child, wildcard, slice,
 * recursive-descent, resolveIndex).
 *
 * @internal stays out of the advertised contract; consumed by Rfc9535Engine.
 */
final class SelectorSteps
{
    public function __construct(
        private SelectorContext $select,
        private JsonPathBudget $budget,
    ) {}

    /**
     * @return list<array<array-key, mixed>>
     */
    public function child(mixed $node, string $name): array
    {
        if (is_array($node) && array_key_exists($name, $node)) {
            /** @var list<array<array-key, mixed>> $matched */
            $matched = [$node[$name]];

            return $matched;
        }

        if (is_object($node) && property_exists($node, $name)) {
            /** @var list<array<array-key, mixed>> $matched */
            $matched = [$node->$name];

            return $matched;
        }

        return [];
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    public function wildcard(mixed $node, int &$visited): array
    {
        $members = [];

        if (is_array($node)) {
            $members = array_values($node);
        } elseif (is_object($node)) {
            $members = array_values((array) $node);
        }

        $out = [];
        foreach ($members as $member) {
            $this->budget->touchNode($visited);
            $out[] = $member;
        }

        /** @var list<array<array-key, mixed>>  $out */
        return $out;
    }

    /**
     * @param  array{start?: int|float|null, end?: int|float|null, step?: int|float|null}  $step
     * @return list<array<array-key, mixed>>
     */
    public function slice(mixed $node, array $step, int &$visited): array
    {
        if (!is_array($node)) {
            return [];
        }

        $count = count($node);
        $startIndex = $this->resolveIndex((int) ($step['start'] ?? 0), $count);
        $endIndex = $this->resolveIndex((int) ($step['end'] ?? 0), $count);
        $stepInterval = (int) ($step['step'] ?? 1);

        $out = [];
        for ($i = $startIndex; $i < $endIndex; $i += $stepInterval) {
            if (!array_key_exists($i, $node)) {
                continue;
            }
            $this->budget->touchNode($visited);
            $out[] = $node[$i];
        }

        /** @var list<array<array-key, mixed>>  $out */
        return $out;
    }

    /**
     * @param  array<array-key, mixed>  $child
     * @return list<array<array-key, mixed>>
     */
    public function recursiveDescent(mixed $node, array $child, string $path, int &$visited, int $depth): array
    {
        $out = [];
        // FIFO queue so descendants are visited in document order.
        $queue = [$node];

        while ($queue !== []) {
            /** @var array<array-key, mixed>  $current */
            $current = array_shift($queue);
            $this->budget->touchNode($visited);

            $matches = $this->select->select(
                ['kind' => 'root', 'steps' => [$child]],
                [$current],
                $path,
                $visited,
                $depth,
            );
            foreach ($matches as $match) {
                $out[] = $match;
            }

            foreach ($current as $value) {
                if (is_array($value) || is_object($value)) {
                    $queue[] = $value;
                }
            }
        }

        /** @var list<array<array-key, mixed>>  $out */
        return $out;
    }

    private function resolveIndex(int $value, int $count): int
    {
        $index = $value;
        if ($index < 0) {
            $index = max(0, $count + $index);
        }

        return min(max(0, $index), $count);
    }
}
