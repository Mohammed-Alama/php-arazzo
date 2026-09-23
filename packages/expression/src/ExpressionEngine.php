<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Expression\Parser as ExpressionParser;

/**
 * Concrete expression facade for static parsing, reference projection, and symbol table generation.
 */
final readonly class ExpressionEngine implements ExpressionEngineInterface
{
    private ExpressionParser $parser;

    public function __construct(?ExpressionParser $parser = null)
    {
        $this->parser = $parser ?? new ExpressionParser();
    }

    public function parseExpression(string $raw): ?ExpressionSyntaxException
    {
        $result = $this->parser->parseOrError($raw);

        return $result instanceof ExpressionSyntaxException ? $result : null;
    }

    public function expressionReferences(string $raw): ?ExpressionReference
    {
        return $this->parser->parseOrError($raw) instanceof ExpressionSyntaxException
            ? null
            : $this->parser->projectReferences($raw);
    }

    public function buildSymbolTable(ArazzoDocument $document): SymbolTable
    {
        return SymbolTable::build($document);
    }
}
