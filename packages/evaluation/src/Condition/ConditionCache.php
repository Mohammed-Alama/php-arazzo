<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Condition;

use Alama\Arazzo\Evaluation\Interfaces\ConditionNode;

/**
 * Bounded in-memory cache of parsed condition ASTs, keyed by the condition string.
 *
 * Only successful parses are cached; syntax errors are re-thrown fresh on each call.
 * The cache is per-instance — no static/global state — so long-lived queue workers
 * do not accumulate unbounded memory. Eviction is FIFO: when the cap is reached, the
 * oldest entry is evicted before inserting the new one.
 *
 * @internal stays out of the advertised contract; consumed by ConditionEvaluator.
 */
final class ConditionCache
{
    /**
     * @param  positive-int  $max  Maximum number of cached conditions.
     */
    public function __construct(private int $max = 256) {}

    /** @var array<string, ConditionNode> */
    private array $store = [];

    /** @var list<string> Insertion order, used for FIFO eviction. */
    private array $order = [];

    public function get(string $condition): ?ConditionNode
    {
        return $this->store[$condition] ?? null;
    }

    public function put(string $condition, ConditionNode $node): void
    {
        if (!isset($this->store[$condition])) {
            $size = count($this->store);
            while ($size >= $this->max) {
                $oldest = array_shift($this->order);
                if ($oldest !== null) {
                    unset($this->store[$oldest]);
                    $size--;
                }
            }
            $this->order[] = $condition;
        }

        $this->store[$condition] = $node;
    }

    public function count(): int
    {
        return count($this->store);
    }
}
