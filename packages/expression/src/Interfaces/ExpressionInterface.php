<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Interfaces;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\SymbolTable;

interface ExpressionInterface
{
    public function parseExpression(string $raw): ?ExpressionSyntaxException;

    public function expressionReferences(string $raw): ?ExpressionReference;

    public function buildSymbolTable(ArazzoDocument $document): SymbolTable;
}
