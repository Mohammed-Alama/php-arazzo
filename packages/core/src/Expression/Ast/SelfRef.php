<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

use Alama\Arazzo\Spec\Expression;

/**
 * 1.1 document identity expression: resolves to the Arazzo document's $self URI.
 */
final readonly class SelfRef extends ExpressionAst {}
