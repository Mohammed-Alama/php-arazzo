<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Data;

/**
 * One segment of a template string produced by {@see ExpressionEngineInterface::parseEmbedded()}.
 *
 * `isExpression` is true when the segment is an embedded expression (e.g. `{$inputs.foo}`);
 * `text` is the bare expression without braces (e.g. `$inputs.foo`) when `isExpression`,
 * or the literal text otherwise. `offset` is the byte offset of the segment start in the input.
 */
final readonly class EmbeddedSegment
{
    public function __construct(
        public bool $isExpression,
        public string $text,
        public int $offset,
    ) {}
}
