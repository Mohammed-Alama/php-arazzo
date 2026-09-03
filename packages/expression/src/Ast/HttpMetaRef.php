<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

final readonly class HttpMetaRef extends ExpressionAst
{
    /** @param 'url'|'method'|'statusCode' $field */
    public function __construct(public string $field) {}
}
