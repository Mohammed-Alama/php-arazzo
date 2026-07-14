<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression\Ast;

final readonly class InputRef extends ExpressionAst
{
    public function __construct(public string $name) {}
}
