<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

use Alama\Arazzo\Contracts\Spec\Expression;

/**
 * 1.1 document identity expression: resolves to the Arazzo document's $self URI.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class SelfRef extends ExpressionAst {}
