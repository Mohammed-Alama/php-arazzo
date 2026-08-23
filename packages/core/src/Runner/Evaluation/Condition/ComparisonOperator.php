<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation\Condition;

enum ComparisonOperator: string
{
    case Eq = 'eq';
    case Neq = 'neq';
    case Gt = 'gt';
    case Gte = 'gte';
    case Lt = 'lt';
    case Lte = 'lte';
}
