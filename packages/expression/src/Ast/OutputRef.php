<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Enum\ReferenceKind;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class OutputRef extends ExpressionAst
{
    public function __construct(
        public string $name,
        public ?string $jsonPointer = null,
    ) {}

    public function mapToExpressionReference(): ExpressionReference
    {
        return new ExpressionReference(
            ReferenceKind::Output,
            $this->name,
            jsonPointer: $this->jsonPointer,
        );
    }
}
