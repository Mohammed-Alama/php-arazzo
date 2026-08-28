<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec\Enum;

enum ExpressionType: string
{
    case JsonPath = 'jsonpath';
    case XPath = 'xpath';
    case JsonPointer = 'jsonpointer';
}
