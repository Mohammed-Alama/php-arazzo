<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class SourceRef extends ExpressionAst
{
    public function __construct(public string $name, public ?string $subPath) {}
}
