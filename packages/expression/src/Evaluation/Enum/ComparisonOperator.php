<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Evaluation\Enum;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
enum ComparisonOperator: string
{
    case Eq = 'eq';
    case Neq = 'neq';
    case Gt = 'gt';
    case Gte = 'gte';
    case Lt = 'lt';
    case Lte = 'lte';
}
