<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Expression\Ast;

final readonly class SourceRef extends ExpressionAst
{
    public function __construct(public string $name, public ?string $subPath)
    {
    }
}
