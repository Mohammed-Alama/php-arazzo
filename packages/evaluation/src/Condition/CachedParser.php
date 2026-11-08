<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Condition;

use Alama\Arazzo\Evaluation\Interfaces\ConditionNode;

/**
 * Parses a condition through the per-evaluator cache.
 *
 * The real condition parser carries mutable token/position state, so the default
 * path creates a fresh parser on each cache miss instead of sharing one across
 * nested evaluations. Only successful parses are cached.
 *
 * @internal stays out of the advertised contract; consumed by ConditionEvaluator.
 */
final class CachedParser implements ParserInterface
{
    public function __construct(
        private readonly Lexer $lexer,
        private readonly ConditionCache $cache,
        private readonly ?ParserInterface $parser = null,
    ) {}

    public function parse(string $condition): ConditionNode
    {
        $ast = $this->cache->get($condition);

        if ($ast === null) {
            $ast = ($this->parser ?? new Parser($this->lexer))->parse($condition);
            $this->cache->put($condition, $ast);
        }

        return $ast;
    }
}
