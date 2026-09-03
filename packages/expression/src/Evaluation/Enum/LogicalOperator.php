<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Evaluation\Enum;

enum LogicalOperator: string
{
    case And = 'and';
    case Or = 'or';
}
