<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Enum\ReferenceKind;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class HttpMetaRef extends ExpressionAst
{
    /** @param 'url'|'method'|'statusCode' $field */
    public function __construct(public string $field) {}

    public function mapToExpressionReference(): ExpressionReference
    {
        return new ExpressionReference(
            ReferenceKind::HttpMeta,
            httpPart: $this->field,
        );
    }
}
