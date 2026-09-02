<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Enum;

enum LogicalOperator: string
{
    case And = 'and';
    case Or = 'or';
}
