<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

final readonly class ComponentRef extends ExpressionAst
{
    public function __construct(public string $type, public string $name)
    {
    }
}
