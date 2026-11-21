<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\JsonPath;

/**
 * Context passed to step implementations so they can re-enter the selector
 * evaluation loop (e.g. recursive descent and union steps invoke `select()`
 * on sub-steps without depending on the concrete engine).
 */
interface SelectorContext
{
    /**
     * @param  array{kind: string, steps: list<array<array-key, mixed>>}  $ast
     * @param  list<array<array-key, mixed>>  $nodes
     * @param  int  $visited  node visits (passed by reference)
     * @param  int  $depth  current selector nesting depth
     * @return list<array<array-key, mixed>>
     */
    public function select(array $ast, array $nodes, string $path, int &$visited, int $depth = 0): array;
}
