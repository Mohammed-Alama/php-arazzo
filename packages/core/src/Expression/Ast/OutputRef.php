<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

final readonly class OutputRef extends ExpressionAst
{
    public function __construct(public string $name)
    {
    }
}
