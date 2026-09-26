<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Enum\ReferenceKind;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class ComponentRef extends ExpressionAst
{
    public function __construct(public string $type, public string $name) {}

    public function mapToExpressionReference(): ExpressionReference
    {
        return new ExpressionReference(
            ReferenceKind::Component,
            $this->type,
            name: $this->name,
        );
    }
}
