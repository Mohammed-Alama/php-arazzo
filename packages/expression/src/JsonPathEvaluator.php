<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Expression\Data\JsonPathBudget;
use Alama\Arazzo\Expression\JsonPath\Rfc9535Engine;

/**
 * Standalone JSONPath evaluation; consumed by the ExpressionEngine facade and exposed
 * through its public jsonPath() method.
 *
 * Backed by a self-contained RFC 9535-conformant engine with query admission
 * budgets, replacing the classic engine and its `[?expr]` → `[?(expr)]` regex
 * translation seam.
 *
 * @param  array<array-key, mixed>|object  $data
 */
final class JsonPathEvaluator
{
    public function __construct(
        private Rfc9535Engine $engine = new Rfc9535Engine(),
    ) {}

    public function evaluate(string $expression, array|object $data): mixed
    {
        $result = $this->engine->find($expression, $data);

        // Single match unwraps to the bare value; multiple matches stay a
        // collection, matching the classic engine's public contract.
        if (is_array($result) && count($result) === 1) {
            return $result[0];
        }

        return $result;
    }

    /** @return list<string> */
    public function supportedVersions(): array
    {
        return $this->engine->supportedVersions();
    }

    public function budget(): JsonPathBudget
    {
        return $this->engine->budget();
    }

    /**
     * Create an evaluator with custom admission budgets.
     *
     * @param  array{maxExpressionLength?: int, maxSelectorDepth?: int, maxNodesVisited?: int, maxResultSetSize?: int}  $limits
     */
    public static function withLimits(array $limits = []): self
    {
        $budget = new JsonPathBudget(
            $limits['maxExpressionLength'] ?? 1000,
            $limits['maxSelectorDepth'] ?? 64,
            $limits['maxNodesVisited'] ?? 10000,
            $limits['maxResultSetSize'] ?? 1000,
        );

        return new self(new Rfc9535Engine($budget));
    }
}
