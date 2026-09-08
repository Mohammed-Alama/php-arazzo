<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Evaluation\Enum;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
enum LogicalOperator: string
{
    case And = 'and';
    case Or = 'or';
}
