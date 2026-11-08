<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Interfaces;

use Alama\Arazzo\Expression\Ast\ExpressionAst;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface ParserInterface
{
    public function parse(string $raw): ExpressionAst;
}
