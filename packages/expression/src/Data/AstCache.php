<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Data;

use Alama\Arazzo\Expression\Ast\ExpressionAst;

/**
 * Bounded in-memory cache of parsed expression ASTs, keyed by the raw expression string.
 *
 * Only successful parses are cached; syntax errors are re-thrown fresh on each call.
 * The cache is per-instance — no static/global state — so long-lived queue workers
 * do not accumulate unbounded memory. Eviction is FIFO: when the cap is reached, the
 * oldest entry is evicted before inserting the new one.
 *
 * @internal stays out of the advertised contract; consumed by ExpressionEvaluator.
 */
final class AstCache
{
    /**
     * @param  positive-int  $max  Maximum number of cached ASTs.
     */
    public function __construct(private int $max = 256) {}

    /** @var array<string, ExpressionAst> */
    private array $store = [];

    /** @var list<string> Insertion order, used for FIFO eviction. */
    private array $order = [];

    public function get(string $raw): ?ExpressionAst
    {
        return $this->store[$raw] ?? null;
    }

    public function put(string $raw, ExpressionAst $ast): void
    {
        if (!isset($this->store[$raw])) {
            $size = count($this->store);
            while ($size >= $this->max) {
                $oldest = array_shift($this->order);
                if ($oldest !== null) {
                    unset($this->store[$oldest]);
                    $size--;
                }
            }
            $this->order[] = $raw;
        }

        $this->store[$raw] = $ast;
    }

    public function count(): int
    {
        return count($this->store);
    }
}
