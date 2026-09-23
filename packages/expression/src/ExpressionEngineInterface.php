<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;

/**
 * Entry-point seam for static Arazzo expression parsing and analysis.
 */
interface ExpressionEngineInterface
{
    /**
     * Parse an expression string. Returns null if valid, or the syntax exception if invalid.
     */
    public function parseExpression(string $raw): ?ExpressionSyntaxException;

    /**
     * Statically inspect what an expression references. Returns null on syntax error.
     */
    public function expressionReferences(string $raw): ?ExpressionReference;

    /**
     * Build the symbol table describing a document's declared workflows, steps, and components.
     */
    public function buildSymbolTable(ArazzoDocument $document): SymbolTable;
}
