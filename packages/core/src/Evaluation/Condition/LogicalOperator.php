<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Condition;

enum LogicalOperator: string
{
    case And = 'and';
    case Or = 'or';
}
