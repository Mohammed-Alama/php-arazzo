<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Enum;

enum ExpressionType: string
{
    case JsonPath = 'jsonpath';
    case XPath = 'xpath';
    case JsonPointer = 'jsonpointer';
}
