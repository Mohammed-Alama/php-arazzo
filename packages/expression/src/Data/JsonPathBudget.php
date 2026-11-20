<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Data;

/**
 * Admission budget for JSONPath evaluation.
 *
 * Bounds expression length, selector depth, nodes visited, and result set size
 * so a pathological query (e.g. `$..*` on a large response) cannot consume
 * unbounded time or memory.
 *
 * @internal stays out of the advertised contract; consumed by JsonPathEvaluator.
 */
final readonly class JsonPathBudget
{
    /**
     * @param  int  $maxExpressionLength  Maximum characters in a JSONPath expression.
     * @param  int  $maxSelectorDepth  Maximum nested selector depth.
     * @param  int  $maxNodesVisited  Maximum document nodes visited during evaluation.
     * @param  int  $maxResultSetSize  Maximum number of result nodes collected.
     */
    public function __construct(
        public int $maxExpressionLength = 1000,
        public int $maxSelectorDepth = 64,
        public int $maxNodesVisited = 10000,
        public int $maxResultSetSize = 1000,
    ) {}

    public static function defaults(): self
    {
        return new self();
    }
}
