<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Enum\ReferenceKind;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
abstract readonly class ExpressionAst
{
    /**
     * Default mapping for any AST node.
     * Override this method in child classes to provide custom reference mapping.
     */
    public function mapToExpressionReference(): ExpressionReference
    {
        return new ExpressionReference(ReferenceKind::Self);
    }
}
